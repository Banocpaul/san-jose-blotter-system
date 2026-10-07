<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $recordStatuses = array_map(
            fn (RecordStatus $status) => $status->value,
            RecordStatus::cases()
        );

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'incident_type_id' => [
                'nullable',
                'integer',
                'exists:incident_types,id',
            ],
            'record_status' => [
                'nullable',
                Rule::in($recordStatuses),
            ],
        ]);

        $query = BlotterCase::query();
        $this->applyFilters($query, $filters);

        /*
        |--------------------------------------------------------------------------
        | Headline incident metrics
        |--------------------------------------------------------------------------
        */

        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS total_incidents')
            ->selectRaw(
                "SUM(CASE WHEN record_status = 'Open' THEN 1 ELSE 0 END) AS open_incidents"
            )
            ->selectRaw(
                "SUM(CASE WHEN record_status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_incidents"
            )
            ->selectRaw(
                "SUM(CASE WHEN record_status = 'Closed' THEN 1 ELSE 0 END) AS closed_incidents"
            )
            ->selectRaw(
                'AVG(CASE WHEN closed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, reported_at, closed_at) / 24 END) AS avg_resolution_days'
            )
            ->first();

        $totalIncidents = (int) ($summary?->total_incidents ?? 0);
        $openIncidents = (int) ($summary?->open_incidents ?? 0);
        $resolvedIncidents = (int) ($summary?->resolved_incidents ?? 0);
        $closedIncidents = (int) ($summary?->closed_incidents ?? 0);
        $avgResolutionDays = $summary?->avg_resolution_days !== null
            ? round((float) $summary->avg_resolution_days, 1)
            : 0;

        $resolutionRate = $totalIncidents > 0
            ? round(($resolvedIncidents / $totalIncidents) * 100, 1)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Incident type distribution
        |--------------------------------------------------------------------------
        */

        $incidentTypeDistribution = (clone $query)
            ->join(
                'incident_types',
                'incident_types.id',
                '=',
                'blotter_cases.incident_type_id'
            )
            ->select([
                'incident_types.id',
                'incident_types.name',
            ])
            ->selectRaw('COUNT(*) AS total')
            ->groupBy(
                'incident_types.id',
                'incident_types.name'
            )
            ->orderByDesc('total')
            ->get();

        $topIncidentType = $incidentTypeDistribution->first();

        /*
        |--------------------------------------------------------------------------
        | Monthly trend
        |--------------------------------------------------------------------------
        */

        $monthlyTrend = (clone $query)
            ->selectRaw(
                "DATE_FORMAT(incident_date, '%Y-%m') AS period"
            )
            ->selectRaw('COUNT(*) AS total')
            ->whereNotNull('incident_date')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Day-of-week pattern
        |--------------------------------------------------------------------------
        */

        $dayOfWeek = (clone $query)
            ->selectRaw('DAYOFWEEK(incident_date) AS day_number')
            ->selectRaw('DAYNAME(incident_date) AS day_name')
            ->selectRaw('COUNT(*) AS total')
            ->whereNotNull('incident_date')
            ->groupBy('day_number', 'day_name')
            ->orderBy('day_number')
            ->get();

        $busiestDay = $dayOfWeek
            ->sortByDesc('total')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Time-of-day pattern
        |--------------------------------------------------------------------------
        */

        $timeOfDay = (clone $query)
            ->whereNotNull('incident_time')
            ->selectRaw(
                "CASE
                    WHEN HOUR(incident_time) BETWEEN 0 AND 5 THEN '12 AM - 5:59 AM'
                    WHEN HOUR(incident_time) BETWEEN 6 AND 11 THEN '6 AM - 11:59 AM'
                    WHEN HOUR(incident_time) BETWEEN 12 AND 17 THEN '12 PM - 5:59 PM'
                    ELSE '6 PM - 11:59 PM'
                END AS time_bucket"
            )
            ->selectRaw(
                "CASE
                    WHEN HOUR(incident_time) BETWEEN 0 AND 5 THEN 1
                    WHEN HOUR(incident_time) BETWEEN 6 AND 11 THEN 2
                    WHEN HOUR(incident_time) BETWEEN 12 AND 17 THEN 3
                    ELSE 4
                END AS bucket_order"
            )
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('time_bucket', 'bucket_order')
            ->orderBy('bucket_order')
            ->get();

        $peakTimeBucket = $timeOfDay
            ->sortByDesc('total')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Top incident locations
        |--------------------------------------------------------------------------
        */

        $topLocations = (clone $query)
            ->whereNotNull('location')
            ->where('location', '<>', '')
            ->select('location')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('location')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topLocation = $topLocations->first();

        /*
        |--------------------------------------------------------------------------
        | Status composition by incident type
        |--------------------------------------------------------------------------
        */

        $statusByTypeRows = (clone $query)
            ->join(
                'incident_types',
                'incident_types.id',
                '=',
                'blotter_cases.incident_type_id'
            )
            ->select([
                'incident_types.id',
                'incident_types.name',
                'blotter_cases.record_status',
            ])
            ->selectRaw('COUNT(*) AS total')
            ->groupBy(
                'incident_types.id',
                'incident_types.name',
                'blotter_cases.record_status'
            )
            ->orderBy('incident_types.name')
            ->get();

        $statusByType = $statusByTypeRows
            ->groupBy('name')
            ->map(function ($rows, $name) {
                $counts = $rows->pluck('total', 'record_status');

                return [
                    'label' => $name,
                    'open' => (int) ($counts[RecordStatus::Open->value] ?? 0),
                    'resolved' => (int) ($counts[RecordStatus::Resolved->value] ?? 0),
                    'closed' => (int) ($counts[RecordStatus::Closed->value] ?? 0),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Resolution performance by incident type
        |--------------------------------------------------------------------------
        */

        $resolutionByType = (clone $query)
            ->join(
                'incident_types',
                'incident_types.id',
                '=',
                'blotter_cases.incident_type_id'
            )
            ->select([
                'incident_types.id',
                'incident_types.name',
            ])
            ->selectRaw('COUNT(*) AS total_cases')
            ->selectRaw(
                "SUM(CASE WHEN blotter_cases.record_status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_cases"
            )
            ->selectRaw(
                'AVG(CASE WHEN blotter_cases.closed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, blotter_cases.reported_at, blotter_cases.closed_at) / 24 END) AS avg_resolution_days'
            )
            ->groupBy(
                'incident_types.id',
                'incident_types.name'
            )
            ->orderByDesc('total_cases')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_cases;
                $resolved = (int) $row->resolved_cases;

                return [
                    'label' => $row->name,
                    'total' => $total,
                    'resolved' => $resolved,
                    'resolution_rate' => $total > 0
                        ? round(($resolved / $total) * 100, 1)
                        : 0,
                    'avg_resolution_days' => $row->avg_resolution_days !== null
                        ? round((float) $row->avg_resolution_days, 1)
                        : 0,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Recent incidents
        |--------------------------------------------------------------------------
        */

        $recentIncidents = (clone $query)
            ->select([
                'blotter_cases.id',
                'blotter_cases.reference_number',
                'blotter_cases.incident_type_id',
                'blotter_cases.incident_date',
                'blotter_cases.incident_time',
                'blotter_cases.location',
                'blotter_cases.case_stage',
                'blotter_cases.record_status',
            ])
            ->with('incidentType:id,name')
            ->latest('incident_date')
            ->latest('id')
            ->limit(10)
            ->get();

        $incidentTypes = IncidentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return view('analytics.incidents', [
            'filters' => $filters,
            'incidentTypes' => $incidentTypes,
            'recordStatuses' => RecordStatus::cases(),
            'totalIncidents' => $totalIncidents,
            'openIncidents' => $openIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'closedIncidents' => $closedIncidents,
            'avgResolutionDays' => $avgResolutionDays,
            'resolutionRate' => $resolutionRate,
            'topIncidentType' => $topIncidentType,
            'busiestDay' => $busiestDay,
            'peakTimeBucket' => $peakTimeBucket,
            'topLocation' => $topLocation,
            'incidentTypeDistribution' => $incidentTypeDistribution,
            'monthlyTrend' => $monthlyTrend,
            'dayOfWeek' => $dayOfWeek,
            'timeOfDay' => $timeOfDay,
            'topLocations' => $topLocations,
            'statusByType' => $statusByType,
            'resolutionByType' => $resolutionByType,
            'recentIncidents' => $recentIncidents,
        ]);
    }

    private function applyFilters(
        Builder $query,
        array $filters
    ): void {
        if (! empty($filters['date_from'])) {
            $query->where(
                'incident_date',
                '>=',
                $filters['date_from']
            );
        }

        if (! empty($filters['date_to'])) {
            $query->where(
                'incident_date',
                '<=',
                $filters['date_to']
            );
        }

        if (! empty($filters['incident_type_id'])) {
            $query->where(
                'incident_type_id',
                (int) $filters['incident_type_id']
            );
        }

        if (! empty($filters['record_status'])) {
            $query->where(
                'record_status',
                $filters['record_status']
            );
        }
    }
}
