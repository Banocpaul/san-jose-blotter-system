<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
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

        $statusValues = collect(
            CaseStatus::cases()
        )
            ->map(
                fn (CaseStatus $status) =>
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

            'status' => [
                'nullable',
                Rule::in(
                    $statusValues
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
        | KPI Cards
        |--------------------------------------------------------------------------
        */

        $totalCases =
            (clone $query)->count();

        $openStatuses = [
            CaseStatus::Pending->value,
            CaseStatus::UnderInvestigation->value,
            CaseStatus::ForMediation->value,
        ];

        $closedStatuses = [
            CaseStatus::Settled->value,
            CaseStatus::Resolved->value,
            CaseStatus::Referred->value,
            CaseStatus::Dismissed->value,
        ];

        $openCases =
            (clone $query)
                ->whereIn(
                    'status',
                    $openStatuses
                )
                ->count();

        $closedCases =
            (clone $query)
                ->whereIn(
                    'status',
                    $closedStatuses
                )
                ->count();

        $settledCases =
            (clone $query)
                ->where(
                    'status',
                    CaseStatus::Settled->value
                )
                ->count();

        $settlementRate =
            $closedCases > 0
                ? round(
                    (
                        $settledCases
                        /
                        $closedCases
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
        | Status Distribution
        |--------------------------------------------------------------------------
        */

        $statusDistribution =
            collect(
                CaseStatus::cases()
            )
                ->map(
                    function (
                        CaseStatus $status
                    ) use ($query) {
                        return [
                            'label' =>
                                $status->value,

                            'total' =>
                                (clone $query)
                                    ->where(
                                        'status',
                                        $status->value
                                    )
                                    ->count(),
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
        | Cases By Sitio
        |--------------------------------------------------------------------------
        */

        $sitioDistribution =
            collect(
                $sitios
            )
                ->map(
                    function (
                        string $sitio
                    ) use ($query) {
                        return [
                            'label' =>
                                $sitio,

                            'total' =>
                                (clone $query)
                                    ->where(
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
                                    )
                                    ->count(),
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

        $councilorWorkload =
            $councilors
                ->map(
                    function (
                        User $councilor
                    ) use ($query) {
                        return [
                            'label' =>
                                $councilor->name,

                            'total' =>
                                (clone $query)
                                    ->whereHas(
                                        'assignments',
                                        function (
                                            Builder $assignment
                                        ) use ($councilor) {
                                            $assignment->where(
                                                'assigned_to',
                                                $councilor->id
                                            );
                                        }
                                    )
                                    ->count(),
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

        $mediationDistribution =
            collect(
                $mediationOutcomes
            )
                ->map(
                    function (
                        string $outcome
                    ) use ($query) {
                        return [
                            'label' =>
                                $outcome,

                            'total' =>
                                (clone $query)
                                    ->whereHas(
                                        'mediationSessions.outcome',
                                        function (
                                            Builder $outcomeQuery
                                        ) use ($outcome) {
                                            $outcomeQuery->where(
                                                'outcome',
                                                $outcome
                                            );
                                        }
                                    )
                                    ->count(),
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

                'statuses' =>
                    CaseStatus::cases(),

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
            $query->whereDate(
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
            $query->whereDate(
                'incident_date',
                '<=',
                $filters['date_to']
            );
        }

        if (
            ! empty(
                $filters['status']
            )
        ) {
            $query->where(
                'status',
                $filters['status']
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
