<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\MediationSession;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $role = $user->role?->slug;

        $data = [
            'role' => $role,
            'totalResidents' => 0,
            'totalCases' => 0,
            'openCases' => 0,
            'resolvedRecordCases' => 0,
            'closedRecordCases' => 0,
            'newCases' => 0,
            'underAssessmentCases' => 0,
            'forMediationCases' => 0,
            'forPangkatCases' => 0,
            'settledResolvedCases' => 0,
            'cfaCases' => 0,
            'closedStageCases' => 0,
            'assignedCases' => 0,
            'scheduledHearings' => 0,
            'todayHearings' => 0,
            'completedHearings' => 0,
            'casesRequiringAction' => 0,
            'resolutionRate' => 0,
            'recentCases' => collect(),
            'upcomingHearings' => collect(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Barangay Captain / Secretary
        |--------------------------------------------------------------------------
        |
        | Management roles receive barangay-wide operational statistics.
        | The Captain dashboard emphasizes executive outcomes while the
        | Secretary view emphasizes current case operations in the Blade view.
        |
        */

        if (in_array($role, ['barangay_captain', 'secretary'], true)) {
            $data = array_merge(
                $data,
                $this->caseSummary(BlotterCase::query()),
                $this->hearingSummary()
            );

            $data['totalResidents'] = Resident::active()->count();
            $data['recentCases'] = $this->recentCases(BlotterCase::query());
            $data['upcomingHearings'] = $this->upcomingHearings();

            return view('dashboard', $data);
        }

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        |
        | Staff see daily encoding and active-workflow indicators. Hearing
        | administration remains excluded because the current RBAC does not
        | grant Staff access to the hearing-management module.
        |
        */

        if ($role === 'staff') {
            $data = array_merge(
                $data,
                $this->caseSummary(BlotterCase::query())
            );

            $data['totalResidents'] = Resident::active()->count();
            $data['recentCases'] = $this->recentCases(BlotterCase::query());

            return view('dashboard', $data);
        }

        /*
        |--------------------------------------------------------------------------
        | Councilor
        |--------------------------------------------------------------------------
        |
        | Councilors only receive statistics for cases currently assigned
        | to them, matching the same record-level visibility used elsewhere.
        |
        */

        if ($role === 'councilor') {
            $assignedQuery = BlotterCase::query()
                ->whereHas(
                    'assignments',
                    function (Builder $query) use ($user) {
                        $query
                            ->where('assigned_to', $user->id)
                            ->whereNull('completed_at');
                    }
                );

            $data = array_merge(
                $data,
                $this->caseSummary($assignedQuery)
            );

            $data['assignedCases'] = $data['totalCases'];
            $data['recentCases'] = $this->recentCases($assignedQuery);

            return view('dashboard', $data);
        }

        /*
        |--------------------------------------------------------------------------
        | Lupon
        |--------------------------------------------------------------------------
        |
        | Lupon members receive statistics only for cases and hearings that
        | are linked to their own mediation assignments.
        |
        */

        if ($role === 'lupon') {
            $mediationCaseQuery = BlotterCase::query()
                ->whereHas(
                    'mediationSessions',
                    fn (Builder $query) =>
                        $query->where('lupon_member_id', $user->id)
                );

            $data = array_merge(
                $data,
                $this->caseSummary($mediationCaseQuery),
                $this->hearingSummary($user->id)
            );

            $data['assignedCases'] = $data['totalCases'];
            $data['recentCases'] = $this->recentCases($mediationCaseQuery);
            $data['upcomingHearings'] = $this->upcomingHearings($user->id);

            return view('dashboard', $data);
        }

        return view('dashboard', $data);
    }

    private function caseSummary(Builder $query): array
    {
        $row = (clone $query)
            ->selectRaw('COUNT(*) AS total_cases')
            ->selectRaw(
                "SUM(CASE WHEN record_status = 'Open' THEN 1 ELSE 0 END) AS open_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN record_status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN record_status = 'Closed' THEN 1 ELSE 0 END) AS closed_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN case_stage = 'New' THEN 1 ELSE 0 END) AS new_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN case_stage = 'Under Assessment' THEN 1 ELSE 0 END) AS under_assessment_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN case_stage = 'For Mediation' THEN 1 ELSE 0 END) AS for_mediation_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN case_stage = 'For Pangkat/Conciliation' THEN 1 ELSE 0 END) AS for_pangkat_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN case_stage = 'Settled/Resolved' THEN 1 ELSE 0 END) AS settled_resolved_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN case_stage = 'For Further Action/CFA' THEN 1 ELSE 0 END) AS cfa_cases"
            )
            ->selectRaw(
                "SUM(CASE WHEN case_stage = 'Closed' THEN 1 ELSE 0 END) AS closed_stage_cases"
            )
            ->selectRaw(
                "SUM(
                    CASE
                        WHEN record_status = 'Open'
                        AND case_stage IN (
                            'Under Assessment',
                            'For Mediation',
                            'For Pangkat/Conciliation',
                            'For Further Action/CFA'
                        )
                        THEN 1
                        ELSE 0
                    END
                ) AS cases_requiring_action"
            )
            ->first();

        $total = (int) ($row?->total_cases ?? 0);
        $resolved = (int) ($row?->resolved_cases ?? 0);

        return [
            'totalCases' => $total,
            'openCases' => (int) ($row?->open_cases ?? 0),
            'resolvedRecordCases' => $resolved,
            'closedRecordCases' => (int) ($row?->closed_cases ?? 0),
            'newCases' => (int) ($row?->new_cases ?? 0),
            'underAssessmentCases' => (int) ($row?->under_assessment_cases ?? 0),
            'forMediationCases' => (int) ($row?->for_mediation_cases ?? 0),
            'forPangkatCases' => (int) ($row?->for_pangkat_cases ?? 0),
            'settledResolvedCases' => (int) ($row?->settled_resolved_cases ?? 0),
            'cfaCases' => (int) ($row?->cfa_cases ?? 0),
            'closedStageCases' => (int) ($row?->closed_stage_cases ?? 0),
            'casesRequiringAction' => (int) ($row?->cases_requiring_action ?? 0),
            'resolutionRate' => $total > 0
                ? round(($resolved / $total) * 100, 1)
                : 0,
        ];
    }

    private function hearingSummary(?int $luponMemberId = null): array
    {
        $query = MediationSession::query();

        if ($luponMemberId !== null) {
            $query->where('lupon_member_id', $luponMemberId);
        }

        $today = today()->toDateString();

        $row = $query
            ->selectRaw(
                "SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) AS scheduled_hearings"
            )
            ->selectRaw(
                "SUM(
                    CASE
                        WHEN status = 'Scheduled'
                        AND scheduled_date = ?
                        THEN 1
                        ELSE 0
                    END
                ) AS today_hearings",
                [$today]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_hearings"
            )
            ->first();

        return [
            'scheduledHearings' => (int) ($row?->scheduled_hearings ?? 0),
            'todayHearings' => (int) ($row?->today_hearings ?? 0),
            'completedHearings' => (int) ($row?->completed_hearings ?? 0),
        ];
    }

    private function recentCases(Builder $query)
    {
        return (clone $query)
            ->select([
                'blotter_cases.id',
                'blotter_cases.reference_number',
                'blotter_cases.incident_type_id',
                'blotter_cases.case_stage',
                'blotter_cases.record_status',
                'blotter_cases.reported_at',
            ])
            ->with([
                'incidentType:id,name',
                'complainants:id,blotter_case_id,first_name,last_name',
                'respondents:id,blotter_case_id,first_name,last_name',
            ])
            ->latest('reported_at')
            ->limit(5)
            ->get();
    }

    private function upcomingHearings(?int $luponMemberId = null)
    {
        $query = MediationSession::query()
            ->select([
                'id',
                'blotter_case_id',
                'hearing_number',
                'scheduled_date',
                'scheduled_time',
                'venue',
                'lupon_member_id',
                'status',
            ])
            ->with([
                'blotterCase:id,reference_number',
                'luponMember:id,name',
            ])
            ->where('status', 'Scheduled')
            ->where(
                'scheduled_date',
                '>=',
                today()->toDateString()
            );

        if ($luponMemberId !== null) {
            $query->where(
                'lupon_member_id',
                $luponMemberId
            );
        }

        return $query
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->limit(5)
            ->get();
    }
}
