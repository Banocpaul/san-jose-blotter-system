<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Models\BlotterCase;
use App\Models\MediationSession;
use App\Models\Resident;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $role = $user->role?->slug;

        /*
        |--------------------------------------------------------------------------
        | Default Dashboard Data
        |--------------------------------------------------------------------------
        */

        $data = [
            'role' => $role,

            'totalResidents' => 0,
            'totalCases' => 0,

            'pendingCases' => 0,
            'underInvestigationCases' => 0,
            'forMediationCases' => 0,

            'settledCases' => 0,
            'resolvedCases' => 0,
            'referredCases' => 0,
            'dismissedCases' => 0,

            'assignedCases' => 0,
            'scheduledHearings' => 0,

            'recentCases' => collect(),
            'upcomingHearings' => collect(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Barangay Captain / Secretary
        |--------------------------------------------------------------------------
        |
        | These roles need an overall operational view.
        |
        */

        if (
            in_array(
                $role,
                [
                    'barangay_captain',
                    'secretary',
                ],
                true
            )
        ) {
            $data['totalResidents'] =
                Resident::active()->count();

            /*
            |--------------------------------------------------------------------------
            | Case Status Counts
            |--------------------------------------------------------------------------
            |
            | Fetch every status total in one query instead of issuing a separate
            | COUNT query for every dashboard card.
            |
            */

            $statusCounts =
                BlotterCase::query()
                    ->select('status')
                    ->selectRaw('COUNT(*) AS total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

            $data['totalCases'] =
                $statusCounts->sum();

            $data['pendingCases'] =
                $statusCounts[
                    CaseStatus::Pending->value
                ] ?? 0;

            $data['underInvestigationCases'] =
                $statusCounts[
                    CaseStatus::UnderInvestigation->value
                ] ?? 0;

            $data['forMediationCases'] =
                $statusCounts[
                    CaseStatus::ForMediation->value
                ] ?? 0;

            $data['settledCases'] =
                $statusCounts[
                    CaseStatus::Settled->value
                ] ?? 0;

            $data['resolvedCases'] =
                $statusCounts[
                    CaseStatus::Resolved->value
                ] ?? 0;

            $data['referredCases'] =
                $statusCounts[
                    CaseStatus::Referred->value
                ] ?? 0;

            $data['dismissedCases'] =
                $statusCounts[
                    CaseStatus::Dismissed->value
                ] ?? 0;

            $data['scheduledHearings'] =
                MediationSession::where(
                    'status',
                    'Scheduled'
                )->count();

            $data['recentCases'] =
                BlotterCase::query()
                    ->select([
                        'id',
                        'reference_number',
                        'incident_type_id',
                        'status',
                        'reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest('reported_at')
                    ->limit(5)
                    ->get();

            $data['upcomingHearings'] =
                MediationSession::query()
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
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->where(
                        'scheduled_date',
                        '>=',
                        today()->toDateString()
                    )
                    ->orderBy(
                        'scheduled_date'
                    )
                    ->orderBy(
                        'scheduled_time'
                    )
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Staff Dashboard
        |--------------------------------------------------------------------------
        */

        if ($role === 'staff') {
            $data['totalResidents'] =
                Resident::active()->count();

            $statusCounts =
                BlotterCase::query()
                    ->select('status')
                    ->selectRaw('COUNT(*) AS total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

            $data['totalCases'] =
                $statusCounts->sum();

            $data['pendingCases'] =
                $statusCounts[
                    CaseStatus::Pending->value
                ] ?? 0;

            $data['underInvestigationCases'] =
                $statusCounts[
                    CaseStatus::UnderInvestigation->value
                ] ?? 0;

            $data['recentCases'] =
                BlotterCase::query()
                    ->select([
                        'id',
                        'reference_number',
                        'incident_type_id',
                        'status',
                        'reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest(
                        'reported_at'
                    )
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Councilor Dashboard
        |--------------------------------------------------------------------------
        |
        | Councilors should only see cases
        | currently assigned to them.
        |
        */

        if ($role === 'councilor') {
            $assignedQuery =
                BlotterCase::whereHas(
                    'assignments',
                    function ($query) use ($user) {

                        $query
                            ->where(
                                'assigned_to',
                                $user->id
                            )
                            ->whereNull(
                                'completed_at'
                            );
                    }
                );

            $assignedStatusCounts =
                (clone $assignedQuery)
                    ->select('status')
                    ->selectRaw('COUNT(*) AS total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

            $data['assignedCases'] =
                $assignedStatusCounts->sum();

            $data['underInvestigationCases'] =
                $assignedStatusCounts[
                    CaseStatus::UnderInvestigation->value
                ] ?? 0;

            $data['pendingCases'] =
                $assignedStatusCounts[
                    CaseStatus::Pending->value
                ] ?? 0;

            $data['recentCases'] =
                (clone $assignedQuery)
                    ->select([
                        'blotter_cases.id',
                        'blotter_cases.reference_number',
                        'blotter_cases.incident_type_id',
                        'blotter_cases.status',
                        'blotter_cases.reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest(
                        'reported_at'
                    )
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Lupon Dashboard
        |--------------------------------------------------------------------------
        |
        | Lupon members only see mediation cases
        | and hearings assigned to their account.
        |
        */

        if ($role === 'lupon') {
            $mediationCaseQuery =
                BlotterCase::whereHas(
                    'mediationSessions',
                    function ($query) use ($user) {

                        $query->where(
                            'lupon_member_id',
                            $user->id
                        );
                    }
                );

            $mediationStatusCounts =
                (clone $mediationCaseQuery)
                    ->select('status')
                    ->selectRaw('COUNT(*) AS total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

            $data['assignedCases'] =
                $mediationStatusCounts->sum();

            $data['forMediationCases'] =
                $mediationStatusCounts[
                    CaseStatus::ForMediation->value
                ] ?? 0;

            $data['scheduledHearings'] =
                MediationSession::where(
                    'lupon_member_id',
                    $user->id
                )
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->count();

            $data['recentCases'] =
                (clone $mediationCaseQuery)
                    ->select([
                        'blotter_cases.id',
                        'blotter_cases.reference_number',
                        'blotter_cases.incident_type_id',
                        'blotter_cases.status',
                        'blotter_cases.reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest(
                        'reported_at'
                    )
                    ->limit(5)
                    ->get();

            $data['upcomingHearings'] =
                MediationSession::query()
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
                    ->where(
                        'lupon_member_id',
                        $user->id
                    )
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->where(
                        'scheduled_date',
                        '>=',
                        today()->toDateString()
                    )
                    ->orderBy(
                        'scheduled_date'
                    )
                    ->orderBy(
                        'scheduled_time'
                    )
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        return view(
            'dashboard',
            $data
        );
    }
}