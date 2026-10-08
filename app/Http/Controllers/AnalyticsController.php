<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function index(Request $request, CaseAnalyticsService $analytics)
    {
        $sitios = ['Sitio 1', 'Sitio 2', 'Sitio 3', 'Sitio 4'];
        $mediationOutcomes = ['Settled', 'Referred', 'Rescheduled', 'No Agreement', 'Dismissed'];
        $filters = $request->validate([
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

        return view('analytics.index', array_merge(
            $analytics->summarize($query, $filters, $sitios, $councilors, $mediationOutcomes, $targetDays),
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
