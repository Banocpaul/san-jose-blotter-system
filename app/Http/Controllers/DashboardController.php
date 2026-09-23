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
                Resident::where(
                    'is_active',
                    true
                )
                    ->whereHas(
                        'complaints'
                    )
                    ->count();

            $data['totalCases'] =
                BlotterCase::count();

            $data['pendingCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::Pending->value
                )->count();

            $data['underInvestigationCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::UnderInvestigation->value
                )->count();

            $data['forMediationCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::ForMediation->value
                )->count();

            $data['settledCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::Settled->value
                )->count();

            $data['resolvedCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::Resolved->value
                )->count();

            $data['referredCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::Referred->value
                )->count();

            $data['dismissedCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::Dismissed->value
                )->count();

            $data['scheduledHearings'] =
                MediationSession::where(
                    'status',
                    'Scheduled'
                )->count();

            $data['recentCases'] =
                BlotterCase::with([
                    'incidentType',
                    'complainants',
                    'respondents',
                ])
                    ->latest('reported_at')
                    ->limit(5)
                    ->get();

            $data['upcomingHearings'] =
                MediationSession::with([
                    'blotterCase',
                    'luponMember',
                ])
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->whereDate(
                        'scheduled_date',
                        '>=',
                        today()
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
                Resident::where(
                    'is_active',
                    true
                )
                    ->whereHas(
                        'complaints'
                    )
                    ->count();

            $data['totalCases'] =
                BlotterCase::count();

            $data['pendingCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::Pending->value
                )->count();

            $data['underInvestigationCases'] =
                BlotterCase::where(
                    'status',
                    CaseStatus::UnderInvestigation->value
                )->count();

            $data['recentCases'] =
                BlotterCase::with([
                    'incidentType',
                    'complainants',
                    'respondents',
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

            $data['assignedCases'] =
                (clone $assignedQuery)
                    ->count();

            $data['underInvestigationCases'] =
                (clone $assignedQuery)
                    ->where(
                        'status',
                        CaseStatus::UnderInvestigation->value
                    )
                    ->count();

            $data['pendingCases'] =
                (clone $assignedQuery)
                    ->where(
                        'status',
                        CaseStatus::Pending->value
                    )
                    ->count();

            $data['recentCases'] =
                (clone $assignedQuery)
                    ->with([
                        'incidentType',
                        'complainants',
                        'respondents',
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

            $data['assignedCases'] =
                (clone $mediationCaseQuery)
                    ->count();

            $data['forMediationCases'] =
                (clone $mediationCaseQuery)
                    ->where(
                        'status',
                        CaseStatus::ForMediation->value
                    )
                    ->count();

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
                    ->with([
                        'incidentType',
                        'complainants',
                        'respondents',
                    ])
                    ->latest(
                        'reported_at'
                    )
                    ->limit(5)
                    ->get();

            $data['upcomingHearings'] =
                MediationSession::with([
                    'blotterCase',
                    'luponMember',
                ])
                    ->where(
                        'lupon_member_id',
                        $user->id
                    )
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->whereDate(
                        'scheduled_date',
                        '>=',
                        today()
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