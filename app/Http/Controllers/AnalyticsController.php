<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        $caseStageValues = collect(
            CaseStage::cases()
        )
            ->map(
                fn (CaseStage $stage) =>
                    $stage->value
            )
            ->values()
            ->all();

        $recordStatusValues = collect(
            RecordStatus::cases()
        )
            ->map(
                fn (RecordStatus $status) =>
                    $status->value
            )
            ->values()
            ->all();

        $mediationOutcomes = [
            'Settled',
            'Referred',
            'Rescheduled',
            'No Agreement',
            'Dismissed',
        ];

        $sitios = [
            'Sitio 1',
            'Sitio 2',
            'Sitio 3',
            'Sitio 4',
        ];

        $filters = $request->validate([
            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'case_stage' => [
                'nullable',
                Rule::in(
                    $caseStageValues
                ),
            ],

            'record_status' => [
                'nullable',
                Rule::in(
                    $recordStatusValues
                ),
            ],

            'incident_type_id' => [
                'nullable',
                'integer',
                'exists:incident_types,id',
            ],

            'sitio' => [
                'nullable',
                Rule::in(
                    $sitios
                ),
            ],

            'councilor_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'mediation_outcome' => [
                'nullable',
                Rule::in(
                    $mediationOutcomes
                ),
            ],
        ]);

        $query =
            BlotterCase::query();

        $this->applyFilters(
            $query,
            $filters
        );

        /*
        |--------------------------------------------------------------------------
        | KPI + Record Status Distribution
        |--------------------------------------------------------------------------
        */

        $recordStatusCounts =
            (clone $query)
                ->select(
                    'blotter_cases.record_status'
                )
                ->selectRaw(
                    'COUNT(*) AS total'
                )
                ->groupBy(
                    'blotter_cases.record_status'
                )
                ->pluck(
                    'total',
                    'blotter_cases.record_status'
                );

        $stageCounts =
            (clone $query)
                ->select(
                    'blotter_cases.case_stage'
                )
                ->selectRaw(
                    'COUNT(*) AS total'
                )
                ->groupBy(
                    'blotter_cases.case_stage'
                )
                ->pluck(
                    'total',
                    'blotter_cases.case_stage'
                );

        $totalCases =
            (int) $recordStatusCounts->sum();

        $openCases =
            (int) (
                $recordStatusCounts[
                    RecordStatus::Open->value
                ] ?? 0
            );

        $resolvedCases =
            (int) (
                $recordStatusCounts[
                    RecordStatus::Resolved->value
                ] ?? 0
            );

        $closedCases =
            (int) (
                $recordStatusCounts[
                    RecordStatus::Closed->value
                ] ?? 0
            );

        $settledCases =
            (int) (
                $stageCounts[
                    CaseStage::SettledResolved->value
                ] ?? 0
            );

        $completedCases =
            $resolvedCases
            +
            $closedCases;

        $settlementRate =
            $completedCases > 0
                ? round(
                    (
                        $settledCases
                        /
                        $completedCases
                    ) * 100,
                    1
                )
                : 0;

        $avgResolutionDays =
            (clone $query)
                ->whereNotNull(
                    'closed_at'
                )
                ->selectRaw(
                    'AVG(TIMESTAMPDIFF(HOUR, reported_at, closed_at)) / 24 AS avg_days'
                )
                ->value(
                    'avg_days'
                );

        $avgResolutionDays =
            $avgResolutionDays !== null
                ? round(
                    (float) $avgResolutionDays,
                    1
                )
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Cases Over Time
        |--------------------------------------------------------------------------
        */

        $caseTrend =
            (clone $query)
                ->selectRaw(
                    "DATE_FORMAT(incident_date, '%Y-%m') AS period"
                )
                ->selectRaw(
                    'COUNT(*) AS total'
                )
                ->groupBy(
                    'period'
                )
                ->orderBy(
                    'period'
                )
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Record Status Distribution
        |--------------------------------------------------------------------------
        */

        $statusDistribution =
            collect(
                RecordStatus::cases()
            )
                ->map(
                    function (
                        RecordStatus $status
                    ) use ($recordStatusCounts) {
                        return [
                            'label' =>
                                $status->value,

                            'total' =>
                                (int) (
                                    $recordStatusCounts[
                                        $status->value
                                    ] ?? 0
                                ),
                        ];
                    }
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Incident Type Distribution
        |--------------------------------------------------------------------------
        */

        $incidentDistribution =
            (clone $query)
                ->join(
                    'incident_types',
                    'incident_types.id',
                    '=',
                    'blotter_cases.incident_type_id'
                )
                ->select(
                    'incident_types.name'
                )
                ->selectRaw(
                    'COUNT(*) AS total'
                )
                ->groupBy(
                    'incident_types.id',
                    'incident_types.name'
                )
                ->orderByDesc(
                    'total'
                )
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Reusable Filtered Case IDs
        |--------------------------------------------------------------------------
        |
        | The remaining relationship-based analytics use this filtered ID
        | subquery so that we can aggregate them in SQL rather than issuing one
        | query per Sitio, Councilor, or mediation outcome.
        |
        */

        $filteredCaseIds =
            (clone $query)
                ->select(
                    'blotter_cases.id'
                );

        /*
        |--------------------------------------------------------------------------
        | Cases By Sitio
        |--------------------------------------------------------------------------
        |
        | Combine complainant/respondent sitios, then count distinct matching
        | cases in one grouped query.
        |
        */

        $partySitios =
            DB::table(
                'case_complainants'
            )
                ->select([
                    'blotter_case_id',
                    'sitio',
                ])
                ->whereNotNull(
                    'sitio'
                )
                ->unionAll(
                    DB::table(
                        'case_respondents'
                    )
                        ->select([
                            'blotter_case_id',
                            'sitio',
                        ])
                        ->whereNotNull(
                            'sitio'
                        )
                );

        $sitioCounts =
            DB::query()
                ->fromSub(
                    $partySitios,
                    'party_sitios'
                )
                ->joinSub(
                    clone $filteredCaseIds,
                    'filtered_cases',
                    'filtered_cases.id',
                    '=',
                    'party_sitios.blotter_case_id'
                )
                ->whereIn(
                    'party_sitios.sitio',
                    $sitios
                )
                ->select(
                    'party_sitios.sitio'
                )
                ->selectRaw(
                    'COUNT(DISTINCT party_sitios.blotter_case_id) AS total'
                )
                ->groupBy(
                    'party_sitios.sitio'
                )
                ->pluck(
                    'total',
                    'party_sitios.sitio'
                );

        $sitioDistribution =
            collect(
                $sitios
            )
                ->map(
                    function (
                        string $sitio
                    ) use ($sitioCounts) {
                        return [
                            'label' =>
                                $sitio,

                            'total' =>
                                (int) (
                                    $sitioCounts[
                                        $sitio
                                    ] ?? 0
                                ),
                        ];
                    }
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Councilor Workload
        |--------------------------------------------------------------------------
        */

        $councilors =
            User::with(
                'role'
            )
                ->whereHas(
                    'role',
                    function (
                        Builder $roleQuery
                    ) {
                        $roleQuery->where(
                            'slug',
                            'councilor'
                        );
                    }
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'name'
                )
                ->get();

        $councilorIds =
            $councilors
                ->pluck('id')
                ->all();

        $councilorWorkloadCounts =
            empty($councilorIds)
                ? collect()
                : DB::table(
                    'case_assignments'
                )
                    ->joinSub(
                        clone $filteredCaseIds,
                        'filtered_cases',
                        'filtered_cases.id',
                        '=',
                        'case_assignments.blotter_case_id'
                    )
                    ->whereIn(
                        'case_assignments.assigned_to',
                        $councilorIds
                    )
                    ->select(
                        'case_assignments.assigned_to'
                    )
                    ->selectRaw(
                        'COUNT(DISTINCT case_assignments.blotter_case_id) AS total'
                    )
                    ->groupBy(
                        'case_assignments.assigned_to'
                    )
                    ->pluck(
                        'total',
                        'case_assignments.assigned_to'
                    );

        $councilorWorkload =
            $councilors
                ->map(
                    function (
                        User $councilor
                    ) use ($councilorWorkloadCounts) {
                        return [
                            'label' =>
                                $councilor->name,

                            'total' =>
                                (int) (
                                    $councilorWorkloadCounts[
                                        $councilor->id
                                    ] ?? 0
                                ),
                        ];
                    }
                )
                ->sortByDesc(
                    'total'
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Mediation Outcomes
        |--------------------------------------------------------------------------
        */

        $mediationOutcomeCounts =
            DB::table(
                'mediation_sessions'
            )
                ->join(
                    'mediation_outcomes',
                    'mediation_outcomes.mediation_session_id',
                    '=',
                    'mediation_sessions.id'
                )
                ->joinSub(
                    clone $filteredCaseIds,
                    'filtered_cases',
                    'filtered_cases.id',
                    '=',
                    'mediation_sessions.blotter_case_id'
                )
                ->whereIn(
                    'mediation_outcomes.outcome',
                    $mediationOutcomes
                )
                ->select(
                    'mediation_outcomes.outcome'
                )
                ->selectRaw(
                    'COUNT(DISTINCT mediation_sessions.blotter_case_id) AS total'
                )
                ->groupBy(
                    'mediation_outcomes.outcome'
                )
                ->pluck(
                    'total',
                    'mediation_outcomes.outcome'
                );

        $mediationDistribution =
            collect(
                $mediationOutcomes
            )
                ->map(
                    function (
                        string $outcome
                    ) use ($mediationOutcomeCounts) {
                        return [
                            'label' =>
                                $outcome,

                            'total' =>
                                (int) (
                                    $mediationOutcomeCounts[
                                        $outcome
                                    ] ?? 0
                                ),
                        ];
                    }
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Recent Matching Cases
        |--------------------------------------------------------------------------
        */

        $recentCases =
            (clone $query)
                ->with([
                    'incidentType',
                    'currentAssignment.assignedOfficer',
                ])
                ->latest(
                    'reported_at'
                )
                ->limit(10)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Filter Options
        |--------------------------------------------------------------------------
        */

        $incidentTypes =
            IncidentType::where(
                'is_active',
                true
            )
                ->orderBy(
                    'name'
                )
                ->get();

        return view(
            'analytics.index',
            [
                'filters' =>
                    $filters,

                'incidentTypes' =>
                    $incidentTypes,

                'caseStages' =>
                    CaseStage::cases(),

                'recordStatuses' =>
                    RecordStatus::cases(),

                'sitios' =>
                    $sitios,

                'councilors' =>
                    $councilors,

                'mediationOutcomes' =>
                    $mediationOutcomes,

                'totalCases' =>
                    $totalCases,

                'openCases' =>
                    $openCases,

                'closedCases' =>
                    $closedCases,

                'settlementRate' =>
                    $settlementRate,

                'avgResolutionDays' =>
                    $avgResolutionDays,

                'caseTrend' =>
                    $caseTrend,

                'statusDistribution' =>
                    $statusDistribution,

                'incidentDistribution' =>
                    $incidentDistribution,

                'sitioDistribution' =>
                    $sitioDistribution,

                'councilorWorkload' =>
                    $councilorWorkload,

                'mediationDistribution' =>
                    $mediationDistribution,

                'recentCases' =>
                    $recentCases,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Shared Filter Logic
    |--------------------------------------------------------------------------
    */

    private function applyFilters(
        Builder $query,
        array $filters
    ): void {
        if (
            ! empty(
                $filters['date_from']
            )
        ) {
            $query->where(
                'incident_date',
                '>=',
                $filters['date_from']
            );
        }

        if (
            ! empty(
                $filters['date_to']
            )
        ) {
            $query->where(
                'incident_date',
                '<=',
                $filters['date_to']
            );
        }

        if (
            ! empty(
                $filters['case_stage']
            )
        ) {
            $query->where(
                'case_stage',
                $filters['case_stage']
            );
        }

        if (
            ! empty(
                $filters['record_status']
            )
        ) {
            $query->where(
                'record_status',
                $filters['record_status']
            );
        }

        if (
            ! empty(
                $filters['incident_type_id']
            )
        ) {
            $query->where(
                'incident_type_id',
                $filters['incident_type_id']
            );
        }

        if (
            ! empty(
                $filters['sitio']
            )
        ) {
            $sitio =
                $filters['sitio'];

            $query->where(
                function (
                    Builder $builder
                ) use ($sitio) {
                    $builder
                        ->whereHas(
                            'complainants',
                            function (
                                Builder $party
                            ) use ($sitio) {
                                $party->where(
                                    'sitio',
                                    $sitio
                                );
                            }
                        )
                        ->orWhereHas(
                            'respondents',
                            function (
                                Builder $party
                            ) use ($sitio) {
                                $party->where(
                                    'sitio',
                                    $sitio
                                );
                            }
                        );
                }
            );
        }

        if (
            ! empty(
                $filters['councilor_id']
            )
        ) {
            $councilorId =
                (int) $filters['councilor_id'];

            $query->whereHas(
                'assignments',
                function (
                    Builder $assignment
                ) use ($councilorId) {
                    $assignment->where(
                        'assigned_to',
                        $councilorId
                    );
                }
            );
        }

        if (
            ! empty(
                $filters['mediation_outcome']
            )
        ) {
            $outcome =
                $filters[
                    'mediation_outcome'
                ];

            $query->whereHas(
                'mediationSessions.outcome',
                function (
                    Builder $outcomeQuery
                ) use ($outcome) {
                    $outcomeQuery->where(
                        'outcome',
                        $outcome
                    );
                }
            );
        }
    }
}
