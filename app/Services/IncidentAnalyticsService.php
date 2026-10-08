<?php

namespace App\Services;

use App\Enums\RecordStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class IncidentAnalyticsService
{
    public function summarize(Builder $query, ?Builder $previousQuery, ?int $year,
        ?CarbonImmutable $start, ?CarbonImmutable $end, CarbonImmutable $asOf): array
    {
        $metrics = $this->metrics($query, $asOf);
        $previousMetrics = $previousQuery === null ? null : $this->metrics($previousQuery, $asOf);
        $comparisons = [];
        foreach (['totalIncidents', 'resolutionRate', 'avgResolutionDays', 'repeatIncidentRate'] as $key) {
            $current = $metrics[$key];
            $previous = $previousMetrics[$key] ?? null;
            $isRate = in_array($key, ['resolutionRate', 'repeatIncidentRate'], true);
            $comparisons[$key] = $current === null || $previous === null || (! $isRate && $previous == 0)
                ? null : round($isRate ? $current - $previous : ($current - $previous) / $previous * 100, 1);
        }
        $incidentTypeDistribution = (clone $query)->toBase()
            ->join('incident_types', 'incident_types.id', '=', 'blotter_cases.incident_type_id')
            ->select('incident_types.name as label')->selectRaw('COUNT(*) AS total')
            ->groupBy('incident_types.id', 'incident_types.name')->orderByDesc('total')->get();
        $month = DB::getDriverName() === 'sqlite' ? "strftime('%Y-%m', incident_date)" : "DATE_FORMAT(incident_date, '%Y-%m')";
        $months = (clone $query)->toBase()->selectRaw("$month AS period, COUNT(*) AS total")
            ->groupBy('period')->orderBy('period')->pluck('total', 'period');
        $monthlyTrend = collect();
        $chartStart = $year === null ? ($start ?? ($months->isNotEmpty() ? CarbonImmutable::parse($months->keys()->first()) : null)) : CarbonImmutable::create($year, 1, 1);
        $chartEnd = $year === null ? ($end ?? ($months->isNotEmpty() ? CarbonImmutable::parse($months->keys()->last()) : null)) : $chartStart->endOfYear();
        if ($chartStart !== null && $chartEnd !== null && $chartStart <= $chartEnd) {
            for ($period = $chartStart->startOfMonth(); $period <= $chartEnd; $period = $period->addMonth()) {
                $monthlyTrend->push(['label' => $year === null ? $period->format('M Y') : $period->format('M'),
                    'total' => (int) ($months[$period->format('Y-m')] ?? 0)]);
            }
        }
        $dayNumber = DB::getDriverName() === 'sqlite' ? "CAST(strftime('%w', incident_date) AS INTEGER)" : '(DAYOFWEEK(incident_date) - 1)';
        $days = (clone $query)->toBase()->selectRaw("$dayNumber AS day_number, COUNT(*) AS total")
            ->groupBy('day_number')->pluck('total', 'day_number');
        $dayOfWeek = collect([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'])
            ->map(fn ($label, $day) => ['label' => $label, 'total' => (int) ($days[$day] ?? 0)])->values();
        $caseIds = (clone $query)->select('blotter_cases.id');
        $sitios = ['Sitio 1', 'Sitio 2', 'Sitio 3', 'Sitio 4'];
        $partySitios = DB::table('case_complainants')->select('blotter_case_id', 'sitio')
            ->unionAll(DB::table('case_respondents')->select('blotter_case_id', 'sitio'));
        $sitioCounts = DB::query()->fromSub($partySitios, 'parties')->joinSub(clone $caseIds, 'cases', 'cases.id', '=', 'parties.blotter_case_id')
            ->whereIn('sitio', $sitios)->select('sitio')->selectRaw('COUNT(DISTINCT parties.blotter_case_id) AS total')
            ->groupBy('sitio')->pluck('total', 'sitio');
        $sitioDistribution = collect($sitios)->map(fn ($sitio) => ['label' => $sitio, 'total' => (int) ($sitioCounts[$sitio] ?? 0)]);
        $outcome = "CASE WHEN record_status = 'Resolved' THEN 'Settled / Resolved' "
            ."WHEN record_status = 'Open' AND case_stage IN ('For Mediation', 'For Pangkat/Conciliation') THEN 'Under Mediation' "
            ."WHEN case_stage = 'For Further Action/CFA' THEN 'CFA / Referred' "
            ."WHEN record_status = 'Closed' OR case_stage = 'Closed' THEN 'Dismissed / Other Closed' ELSE 'Unresolved Open' END";
        $outcomes = (clone $query)->toBase()->selectRaw("$outcome AS outcome, COUNT(*) AS total")
            ->groupBy('outcome')->pluck('total', 'outcome');
        $outcomeDistribution = collect(['Settled / Resolved', 'Under Mediation', 'CFA / Referred', 'Dismissed / Other Closed', 'Unresolved Open'])
            ->map(fn ($label) => ['label' => $label, 'total' => (int) ($outcomes[$label] ?? 0)]);
        $resolutionByType = (clone $this->resolutionQuery($query, $asOf))->toBase()
            ->join('incident_types', 'incident_types.id', '=', 'blotter_cases.incident_type_id')
            ->select('incident_types.name as label')->selectRaw('COUNT(*) AS samples, AVG('.$this->resolutionDuration().') AS avg_days')
            ->groupBy('incident_types.id', 'incident_types.name')->get();
        $recentIncidents = (clone $query)->with('incidentType')->latest('incident_date')->latest('id')->limit(10)->get();

        return array_merge($metrics, compact('previousMetrics', 'comparisons', 'monthlyTrend', 'dayOfWeek',
            'incidentTypeDistribution', 'sitioDistribution', 'outcomeDistribution', 'resolutionByType', 'recentIncidents'));
    }

    private function metrics(Builder $query, CarbonImmutable $asOf): array
    {
        $totalIncidents = (clone $query)->count();
        $resolvedIncidents = (clone $query)->where('record_status', RecordStatus::Resolved->value)->count();
        $resolutionRate = $totalIncidents === 0 ? null : round($resolvedIncidents / $totalIncidents * 100, 1);
        $resolution = $this->resolutionQuery($query, $asOf)->selectRaw('COUNT(*) AS samples, AVG('.$this->resolutionDuration().') AS avg_days')->first();
        $resolutionSamples = (int) $resolution->samples;
        $avgResolutionDays = $resolutionSamples === 0 ? null : round((float) $resolution->avg_days, 1);
        $linked = (clone $query)->whereExists(function (QueryBuilder $participants) {
            $participants->selectRaw('1')->fromSub($this->participants(), 'participants')
                ->whereColumn('participants.blotter_case_id', 'blotter_cases.id');
        });
        $linkedIncidents = (clone $linked)->count();
        // Search earlier non-deleted incidents across the full history, including
        // years outside the current filter. Count the current case once, not once per party.
        $repeatIncidents = (clone $linked)->whereExists(function (QueryBuilder $history) {
            $history->selectRaw('1')->fromSub($this->participants(), 'current_parties')
                ->joinSub($this->participants(), 'prior_parties', 'prior_parties.resident_id', '=', 'current_parties.resident_id')
                ->join('blotter_cases as prior_cases', 'prior_cases.id', '=', 'prior_parties.blotter_case_id')
                ->whereColumn('current_parties.blotter_case_id', 'blotter_cases.id')
                ->whereNull('prior_cases.deleted_at')
                ->where(function (QueryBuilder $earlier) {
                    $earlier->whereColumn('prior_cases.incident_date', '<', 'blotter_cases.incident_date')
                        ->orWhere(function (QueryBuilder $sameDay) {
                            $sameDay->whereColumn('prior_cases.incident_date', 'blotter_cases.incident_date')
                                ->whereRaw("COALESCE(prior_cases.incident_time, '00:00:00') < COALESCE(blotter_cases.incident_time, '00:00:00')");
                        })->orWhere(function (QueryBuilder $sameTime) {
                            $sameTime->whereColumn('prior_cases.incident_date', 'blotter_cases.incident_date')
                                ->whereRaw("COALESCE(prior_cases.incident_time, '00:00:00') = COALESCE(blotter_cases.incident_time, '00:00:00')")
                                ->whereColumn('prior_cases.id', '<', 'blotter_cases.id');
                        });
                });
        })->count();
        $repeatIncidentRate = $linkedIncidents === 0 ? null : round($repeatIncidents / $linkedIncidents * 100, 1);

        return compact('totalIncidents', 'resolvedIncidents', 'resolutionRate', 'resolutionSamples', 'avgResolutionDays',
            'linkedIncidents', 'repeatIncidents', 'repeatIncidentRate');
    }

    private function participants(): QueryBuilder
    {
        return DB::table('case_complainants')->select('blotter_case_id', 'resident_id')->whereNotNull('resident_id')
            ->union(DB::table('case_respondents')->select('blotter_case_id', 'resident_id')->whereNotNull('resident_id'));
    }

    private function resolutionQuery(Builder $query, CarbonImmutable $asOf): Builder
    {
        $end = $this->resolutionEnd();

        return (clone $query)->leftJoin('case_resolutions as resolutions', 'resolutions.blotter_case_id', '=', 'blotter_cases.id')
            ->where('blotter_cases.record_status', RecordStatus::Resolved->value)->whereNotNull('blotter_cases.reported_at')
            ->whereRaw("$end >= blotter_cases.reported_at")->whereRaw("$end <= ?", [$asOf->toDateTimeString()]);
    }

    private function resolutionEnd(): string
    {
        return "COALESCE(blotter_cases.closed_at, CASE WHEN resolutions.status = 'Completed' THEN resolutions.resolved_at END)";
    }

    private function resolutionDuration(): string
    {
        $end = $this->resolutionEnd();

        return DB::getDriverName() === 'sqlite' ? "(julianday($end) - julianday(blotter_cases.reported_at))"
            : "TIMESTAMPDIFF(SECOND, blotter_cases.reported_at, $end) / 86400.0";
    }
}
