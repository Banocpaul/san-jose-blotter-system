<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\MediationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HearingScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user?->role?->slug;

        if (! in_array($role, [
            'barangay_captain',
            'secretary',
            'lupon',
        ], true)) {
            abort(403);
        }

        $today = now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Schedule Visibility
        |--------------------------------------------------------------------------
        |
        | Captain and Secretary see all schedules.
        | Lupon members see only hearings assigned to their own account.
        |
        */

        $baseQuery = MediationSession::query();

        if ($role === 'lupon') {
            $baseQuery->where(
                'lupon_member_id',
                $user->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | KPI Counts
        |--------------------------------------------------------------------------
        */

        $kpiRow = (clone $baseQuery)
            ->selectRaw(
                "SUM(CASE WHEN status = 'Scheduled' AND scheduled_date > ? THEN 1 ELSE 0 END) AS upcoming_count",
                [$today]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'Scheduled' AND scheduled_date = ? THEN 1 ELSE 0 END) AS today_count",
                [$today]
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count"
            )
            ->first();

        $kpis = [
            'upcoming' => (int) ($kpiRow?->upcoming_count ?? 0),
            'today' => (int) ($kpiRow?->today_count ?? 0),
            'completed' => (int) ($kpiRow?->completed_count ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | Hearing Schedule List
        |--------------------------------------------------------------------------
        */

        $sessions = (clone $baseQuery)
            ->select([
                'mediation_sessions.id',
                'mediation_sessions.blotter_case_id',
                'mediation_sessions.hearing_number',
                'mediation_sessions.proceeding_type',
                'mediation_sessions.scheduled_date',
                'mediation_sessions.scheduled_time',
                'mediation_sessions.venue',
                'mediation_sessions.lupon_member_id',
                'mediation_sessions.status',
            ])
            ->with([
                'blotterCase:id,reference_number,incident_type_id',
                'blotterCase.incidentType:id,name',
                'blotterCase.complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'blotterCase.respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'luponMember:id,name',
                'outcome:id,mediation_session_id,outcome',
            ])
            ->when(
                $request->filled('search'),
                function (Builder $query) use ($request) {
                    $search = trim(
                        $request
                            ->string('search')
                            ->toString()
                    );

                    $query->where(
                        function (Builder $inner) use ($search) {
                            $inner
                                ->whereHas(
                                    'blotterCase',
                                    fn (Builder $case) =>
                                        $case->where(
                                            'reference_number',
                                            'like',
                                            "%{$search}%"
                                        )
                                )
                                ->orWhereHas(
                                    'blotterCase.complainants',
                                    fn (Builder $person) =>
                                        $person
                                            ->where(
                                                'first_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'middle_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'last_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                )
                                ->orWhereHas(
                                    'blotterCase.respondents',
                                    fn (Builder $person) =>
                                        $person
                                            ->where(
                                                'first_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'middle_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhere(
                                                'last_name',
                                                'like',
                                                "%{$search}%"
                                            )
                                )
                                ->orWhereHas(
                                    'luponMember',
                                    fn (Builder $member) =>
                                        $member->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                );
                        }
                    );
                }
            );

        $view = $request
            ->string('view')
            ->toString();

        if ($view === 'upcoming') {
            $sessions
                ->where('status', 'Scheduled')
                ->where('scheduled_date', '>', $today);
        } elseif ($view === 'today') {
            $sessions
                ->where('status', 'Scheduled')
                ->where('scheduled_date', $today);
        } elseif ($view === 'completed') {
            $sessions->where(
                'status',
                'Completed'
            );
        }

        $sessions = $sessions
            ->orderByDesc('scheduled_date')
            ->orderByDesc('scheduled_time')
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Central Create Schedule Form
        |--------------------------------------------------------------------------
        |
        | Scheduling remains owned by MediationController::schedule().
        | This page only supplies valid cases and active Lupon members to that
        | existing workflow so the business rules, summons, attendance records,
        | duplicate-hearing protection, and audit trail stay in one place.
        |
        */

        $eligibleCases = collect();
        $luponMembers = collect();

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
            $eligibleCases = BlotterCase::query()
                ->select([
                    'id',
                    'reference_number',
                    'incident_type_id',
                    'case_stage',
                    'record_status',
                ])
                ->where(
                    'record_status',
                    RecordStatus::Open->value
                )
                ->whereIn(
                    'case_stage',
                    [
                        CaseStage::ForMediation->value,
                        CaseStage::ForPangkatConciliation->value,
                    ]
                )
                ->whereDoesntHave(
                    'mediationSessions',
                    fn (Builder $query) =>
                        $query->where(
                            'status',
                            'Scheduled'
                        )
                )
                ->with([
                    'incidentType:id,name',
                    'complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                    'respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                ])
                ->orderBy('reference_number')
                ->get();

            $luponMembers = User::query()
                ->select([
                    'id',
                    'name',
                    'role_id',
                    'is_active',
                ])
                ->where(
                    'is_active',
                    true
                )
                ->whereHas(
                    'role',
                    fn (Builder $query) =>
                        $query->where(
                            'slug',
                            'lupon'
                        )
                )
                ->orderBy('name')
                ->get();
        }

        return view(
            'hearings.index',
            [
                'sessions' => $sessions,
                'kpis' => $kpis,
                'roleSlug' => $role,
                'eligibleCases' => $eligibleCases,
                'luponMembers' => $luponMembers,
            ]
        );
    }
}
