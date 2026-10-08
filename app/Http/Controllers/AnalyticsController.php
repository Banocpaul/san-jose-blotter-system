<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BusinessIntelligenceService;
use App\Services\CaseAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function index(Request $request, CaseAnalyticsService $analytics)
    {
        $sitios = ['Sitio 1', 'Sitio 2', 'Sitio 3', 'Sitio 4'];
        $mediationOutcomes = ['Settled', 'Referred', 'Rescheduled', 'No Agreement', 'Dismissed'];
        $filters = $request->validate([
            'year' => ['nullable', function ($attribute, $value, $fail) {
                if ($value !== 'all' && (! ctype_digit((string) $value) || (int) $value < 1900 || (int) $value > 2100)) {
                    $fail('Choose a valid reporting year.');
                }
            }],
            'tab' => ['nullable', Rule::in(['operations', 'performance', 'bottlenecks'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'case_stage' => ['nullable', Rule::enum(CaseStage::class)],
            'record_status' => ['nullable', Rule::enum(RecordStatus::class)],
            'incident_type_id' => ['nullable', 'integer', 'exists:incident_types,id'],
            'sitio' => ['nullable', Rule::in($sitios)],
            'councilor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role_id',
                Role::where('slug', 'councilor')->select('id'))],
            'mediation_outcome' => ['nullable', Rule::in($mediationOutcomes)],
            'target_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $query = BlotterCase::query()->visibleTo($request->user());
        $this->applyFilters($query, $filters);
        $councilors = User::whereHas('role', fn (Builder $role) => $role->where('slug', 'councilor'))
            ->orderBy('name')->get();
        $targetDays = isset($filters['target_days']) ? (int) $filters['target_days'] : null;

        $summary = $analytics->summarize($query, $filters, $sitios, $councilors, $mediationOutcomes, $targetDays);

        return view('analytics.index', array_merge(
            $summary,
            app(BusinessIntelligenceService::class)->summarize(clone $query, $filters, $summary['asOf']),
            [
                'filters' => $filters,
                'activeTab' => $filters['tab'] ?? 'operations',
                'incidentTypes' => IncidentType::orderBy('name')->get(),
                'caseStages' => CaseStage::cases(),
                'recordStatuses' => RecordStatus::cases(),
                'sitios' => $sitios,
                'councilors' => $councilors,
                'mediationOutcomes' => $mediationOutcomes,
                'targetDays' => $targetDays,
            ]
        ));
    }

    public function recordSlaStart(Request $request)
    {
        $data = $request->validate([
            'case_id' => ['required', 'integer'],
            'started_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $start = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $data['started_at'], 'Asia/Manila')->utc();
        if ($start > now()) {
            throw ValidationException::withMessages(['started_at' => 'The actual start date cannot be in the future.']);
        }
        DB::transaction(function () use ($data, $start, $request) {
            $case = BlotterCase::query()->visibleTo($request->user())->lockForUpdate()->findOrFail($data['case_id']);
            if ($case->record_status !== RecordStatus::Open || $case->sla_started_at !== null
                || ! in_array($case->case_stage, [CaseStage::ForMediation, CaseStage::ForPangkatConciliation, CaseStage::ForFurtherActionCfa], true)) {
                throw ValidationException::withMessages(['case_id' => 'Choose an open mediation, Pangkat, or further-action case without an SLA start date.']);
            }
            if (($case->reported_at !== null && $start < $case->reported_at)
                || ($case->stage_entered_at !== null && $start < $case->stage_entered_at->copy()->startOfMinute())) {
                throw ValidationException::withMessages(['started_at' => 'The start date cannot precede the report date or the recorded current-stage entry.']);
            }
            $case->sla_started_at = $start;
            $case->save();
            AuditLogService::log('sla_start_recorded', 'Analytics', $data['reason'], $case,
                ['sla_started_at' => null], ['sla_started_at' => $start->toDateTimeString(), 'case_stage' => $case->case_stage->value]);
        });

        return redirect()->route('analytics.index', ['tab' => 'bottlenecks'])->with('success', 'Actual SLA start date recorded.');
    }

    public function extendSla(Request $request)
    {
        $data = $request->validate(['case_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($data, $request) {
            $case = BlotterCase::query()->visibleTo($request->user())->lockForUpdate()->findOrFail($data['case_id']);
            if ($case->record_status !== RecordStatus::Open || $case->case_stage !== CaseStage::ForPangkatConciliation
                || $case->sla_started_at === null || $case->sla_extension_days) {
                throw ValidationException::withMessages(['case_id' => 'Choose an open Pangkat case with an actual start date and no recorded extension.']);
            }
            $case->sla_extension_days = 15;
            $case->sla_extension_reason = $data['reason'];
            $case->save();
            AuditLogService::log('sla_extension_recorded', 'Analytics', $data['reason'], $case,
                ['sla_extension_days' => 0], ['sla_extension_days' => 15, 'reason' => $data['reason']]);
        });

        return redirect()->route('analytics.index', ['tab' => 'bottlenecks'])->with('success', 'Approved 15-day Pangkat extension recorded.');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['date_from' => '>=', 'date_to' => '<='] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $query->where('blotter_cases.incident_date', $operator, $filters[$filter]);
            }
        }
        foreach (['case_stage', 'record_status', 'incident_type_id'] as $column) {
            if (! empty($filters[$column])) {
                $query->where('blotter_cases.'.$column, $filters[$column]);
            }
        }
        if (! empty($filters['sitio'])) {
            $query->where(function (Builder $cases) use ($filters) {
                $cases->whereHas('complainants', fn (Builder $party) => $party->where('sitio', $filters['sitio']))
                    ->orWhereHas('respondents', fn (Builder $party) => $party->where('sitio', $filters['sitio']));
            });
        }
        if (! empty($filters['councilor_id'])) {
            $query->whereHas('currentAssignment', fn (Builder $assignment) => $assignment->where('assigned_to', $filters['councilor_id']));
        }
        if (! empty($filters['mediation_outcome'])) {
            $query->whereHas('mediationSessions.outcome', fn (Builder $outcome) => $outcome->where('outcome', $filters['mediation_outcome']));
        }
    }
}
