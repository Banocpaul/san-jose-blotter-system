<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Services\IncidentAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentAnalyticsController extends Controller
{
    public function index(Request $request, IncidentAnalyticsService $analytics)
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'incident_type_id' => ['nullable', 'integer', 'exists:incident_types,id'],
            'record_status' => ['nullable', Rule::enum(RecordStatus::class)],
        ]);
        $asOf = CarbonImmutable::now();
        $baseQuery = BlotterCase::query()->visibleTo($request->user())->where('incident_date', '<=', $asOf->toDateString());
        $availableYears = (clone $baseQuery)->toBase()->selectRaw('SUBSTR(incident_date, 1, 4) AS year')
            ->distinct()->orderByDesc('year')->pluck('year')->map(fn ($year) => (int) $year);
        // The first visit shows the most recent recorded year. An empty year means all years.
        $selectedYear = $request->has('year')
            ? (isset($filters['year']) ? (int) $filters['year'] : null)
            : ($availableYears->first() ?? (int) $asOf->year);
        if ($selectedYear !== null && ! $availableYears->contains($selectedYear)) {
            $availableYears->push($selectedYear);
            $availableYears = $availableYears->sortDesc()->values();
        }
        $filters['year'] = $selectedYear;
        foreach (['incident_type_id', 'record_status'] as $column) {
            if (! empty($filters[$column])) {
                $baseQuery->where('blotter_cases.'.$column, $filters[$column]);
            }
        }
        $yearStart = $selectedYear === null ? null : CarbonImmutable::create($selectedYear, 1, 1)->startOfDay();
        $yearEnd = $yearStart?->endOfYear()->startOfDay();
        $periodStart = ! empty($filters['date_from']) ? CarbonImmutable::parse($filters['date_from']) : $yearStart;
        $periodEnd = ! empty($filters['date_to']) ? CarbonImmutable::parse($filters['date_to']) : $yearEnd;
        if ($yearStart !== null) {
            $periodStart = $periodStart->max($yearStart);
            $periodEnd = $periodEnd->min($yearEnd);
        }
        $periodEnd = $periodEnd?->min($asOf->startOfDay());
        $query = clone $baseQuery;
        if ($periodStart !== null) {
            $query->where('incident_date', '>=', $periodStart->toDateString());
        }
        if ($periodEnd !== null) {
            $query->where('incident_date', '<=', $periodEnd->toDateString());
        }
        $previousQuery = null;
        $previousStart = null;
        $previousEnd = null;
        if ($periodStart !== null && $periodEnd !== null && $periodStart <= $periodEnd) {
            if ($selectedYear !== null && empty($filters['date_from']) && empty($filters['date_to'])) {
                // Compare the same calendar dates in the preceding year, including year-to-date.
                $previousStart = $periodStart->subYearNoOverflow();
                $previousEnd = $periodEnd->subYearNoOverflow();
            } else {
                $length = (int) $periodStart->diffInDays($periodEnd) + 1;
                $previousEnd = $periodStart->subDay();
                $previousStart = $previousEnd->subDays($length - 1);
            }
            $previousQuery = (clone $baseQuery)->whereBetween('incident_date', [
                $previousStart->toDateString(), $previousEnd->toDateString(),
            ]);
        }

        return view('analytics.incidents', array_merge(
            $analytics->summarize($query, $previousQuery, $selectedYear, $periodStart, $periodEnd, $asOf),
            [
                'filters' => $filters,
                'availableYears' => $availableYears,
                'selectedYear' => $selectedYear,
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
                'previousStart' => $previousStart,
                'previousEnd' => $previousEnd,
                'incidentTypes' => IncidentType::orderBy('name')->get(),
                'recordStatuses' => RecordStatus::cases(),
            ]
        ));
    }
}
