<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Models\BlotterCase;
use App\Models\MediationSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LuponMediationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user?->role?->slug;

        /*
        |--------------------------------------------------------------------------
        | Access Control
        |--------------------------------------------------------------------------
        */
        if (! in_array($role, [
            'barangay_captain',
            'secretary',
            'lupon',
        ], true)) {
            abort(403);
        }

        /*
        |--------------------------------------------------------------------------
        | Active Lupon Case Stages
        |--------------------------------------------------------------------------
        */
        $activeStages = [
            CaseStage::ForMediation->value,
            CaseStage::ForPangkatConciliation->value,
        ];

        /*
        |--------------------------------------------------------------------------
        | Base Case Query
        |--------------------------------------------------------------------------
        |
        | Barangay Captain and Secretary can view all active Lupon cases.
        | Lupon members only see cases assigned to them.
        |
        */
        $baseCases = BlotterCase::query()
            ->whereIn('case_stage', $activeStages);

        if ($role === 'lupon') {
            $baseCases->whereHas(
                'mediationSessions',
                fn (Builder $query) => $query
                    ->where('lupon_member_id', $user->id)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | KPI: Case Stage Counts
        |--------------------------------------------------------------------------
        |
        | One grouped query instead of separate COUNT queries.
        |
        */
        $rawStageCounts = (clone $baseCases)
            ->selectRaw('case_stage, COUNT(*) AS total')
            ->groupBy('case_stage')
            ->pluck('total', 'case_stage');

        /*
        |--------------------------------------------------------------------------
        | KPI: Pending Proceedings
        |--------------------------------------------------------------------------
        */
        $pendingProceedings = MediationSession::query()
            ->where('status', 'Scheduled')
            ->when(
                $role === 'lupon',
                fn (Builder $query) => $query
                    ->where('lupon_member_id', $user->id)
            )
            ->whereHas(
                'blotterCase',
                fn (Builder $query) => $query
                    ->whereIn('case_stage', $activeStages)
            )
            ->count();

        /*
        |--------------------------------------------------------------------------
        | KPI: Settled Cases
        |--------------------------------------------------------------------------
        */
        $settledCases = BlotterCase::query()
            ->where(
                'case_stage',
                CaseStage::SettledResolved->value
            )
            ->whereHas(
                'mediationSessions',
                function (Builder $sessionQuery) use ($role, $user) {
                    if ($role === 'lupon') {
                        $sessionQuery->where(
                            'lupon_member_id',
                            $user->id
                        );
                    }

                    $sessionQuery->whereHas(
                        'outcome',
                        fn (Builder $outcomeQuery) => $outcomeQuery
                            ->where('outcome', 'Settled')
                    );
                }
            )
            ->count();

        $kpis = [
            'for_mediation' => (int) (
                $rawStageCounts[
                    CaseStage::ForMediation->value
                ] ?? 0
            ),

            'for_pangkat' => (int) (
                $rawStageCounts[
                    CaseStage::ForPangkatConciliation->value
                ] ?? 0
            ),

            'pending_proceedings' => $pendingProceedings,

            'settled' => $settledCases,
        ];

        /*
        |--------------------------------------------------------------------------
        | Lupon Case List
        |--------------------------------------------------------------------------
        |
        | Important:
        | mediation_sessions columns are fully qualified because
        | latestOfMany() creates an internal JOIN. Without qualification,
        | columns such as blotter_case_id become ambiguous in MySQL/TiDB.
        |
        */
        $casesQuery = (clone $baseCases)
            ->with([
                'incidentType:id,name',

                'complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',

                'respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',

                'latestMediationSession' => function ($query) {
                    $query->select([
                        'mediation_sessions.id',
                        'mediation_sessions.blotter_case_id',
                        'mediation_sessions.hearing_number',
                        'mediation_sessions.proceeding_type',
                        'mediation_sessions.scheduled_date',
                        'mediation_sessions.scheduled_time',
                        'mediation_sessions.venue',
                        'mediation_sessions.lupon_member_id',
                        'mediation_sessions.status',
                    ]);
                },

                'latestMediationSession.luponMember:id,name',

                'latestMediationSession.outcome:id,mediation_session_id,outcome',
            ])
            ->searchCase(
                $request->string('search')->toString()
            );

        /*
        |--------------------------------------------------------------------------
        | Stage Filter
        |--------------------------------------------------------------------------
        */
        $requestedStage = $request
            ->string('stage')
            ->toString();

        if (
            $requestedStage !== ''
            && in_array(
                $requestedStage,
                $activeStages,
                true
            )
        ) {
            $casesQuery->where(
                'case_stage',
                $requestedStage
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */
        $cases = $casesQuery
            ->latest('blotter_cases.updated_at')
            ->paginate(15)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */
        return view('lupon.index', [
            'cases' => $cases,
            'kpis' => $kpis,
            'activeStages' => $activeStages,
            'roleSlug' => $role,
        ]);
    }
}