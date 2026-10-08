<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CaseAnalyticsService
{
    public function summarize(Builder $query, array $filters, array $sitios, Collection $councilors,
        array $mediationOutcomes, ?int $targetDays): array
    {
        // Keep a single clock for all age and target calculations in this request.
        $asOf = CarbonImmutable::now();
        $statusCounts = (clone $query)->select('record_status')->selectRaw('COUNT(*) AS total')
            ->groupBy('record_status')->pluck('total', 'record_status');
        $stageCounts = (clone $query)->select('case_stage')->selectRaw('COUNT(*) AS total')
            ->groupBy('case_stage')->pluck('total', 'case_stage');
        $totalCases = (int) $statusCounts->sum();
        $openCases = (int) ($statusCounts[RecordStatus::Open->value] ?? 0);
        $resolvedCases = (int) ($statusCounts[RecordStatus::Resolved->value] ?? 0);
        $closedCases = (int) ($statusCounts[RecordStatus::Closed->value] ?? 0);
        $dismissedCases = (int) ($stageCounts[CaseStage::Closed->value] ?? 0);
        $referredCases = (int) ($stageCounts[CaseStage::ForFurtherActionCfa->value] ?? 0);
        $resolutionRate = $totalCases > 0 ? round($resolvedCases / $totalCases * 100, 1) : null;

        // closed_at records the actual outcome. resolved_at is a fallback for legacy
        // completed settlements, not a substitute for missing historical dates.
        $endDate = "COALESCE(blotter_cases.closed_at, CASE WHEN resolutions.status = 'Completed' THEN resolutions.resolved_at END)";
        $duration = $this->daysBetween('blotter_cases.reported_at', $endDate);
        $resolutionStats = (clone $query)
            ->leftJoin('case_resolutions as resolutions', 'resolutions.blotter_case_id', '=', 'blotter_cases.id')
            ->where('blotter_cases.record_status', RecordStatus::Resolved->value)
            ->whereNotNull('blotter_cases.reported_at')
            ->whereRaw("$endDate >= blotter_cases.reported_at")
            ->whereRaw("$endDate <= ?", [$asOf->toDateTimeString()])
            ->selectRaw("COUNT(*) AS samples, AVG($duration) AS avg_days")->first();
        $resolutionSamples = (int) $resolutionStats->samples;
        $avgResolutionDays = $resolutionSamples > 0 ? round((float) $resolutionStats->avg_days, 1) : null;
        $missingResolutionDates = $resolvedCases - $resolutionSamples;

        $month = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', blotter_cases.incident_date)"
            : "DATE_FORMAT(blotter_cases.incident_date, '%Y-%m')";
        $months = (clone $query)->selectRaw("$month AS period, COUNT(*) AS total")
            ->groupBy('period')->orderBy('period')->pluck('total', 'period');
        $caseTrend = collect();
        if ($months->isNotEmpty()) {
            $start = CarbonImmutable::parse($filters['date_from'] ?? $months->keys()->first())->startOfMonth();
            $end = CarbonImmutable::parse($filters['date_to'] ?? $months->keys()->last())->startOfMonth();
            for ($period = $start; $period <= $end; $period = $period->addMonth()) {
                $caseTrend->push(['label' => $period->format('M Y'), 'total' => (int) ($months[$period->format('Y-m')] ?? 0)]);
            }
        }
        $statusDistribution = collect(RecordStatus::cases())->map(fn ($status) => [
            'label' => $status->value, 'total' => (int) ($statusCounts[$status->value] ?? 0),
        ]);
        $stageDistribution = collect(CaseStage::cases())->map(fn ($stage) => [
            'label' => $stage->label(), 'total' => (int) ($stageCounts[$stage->value] ?? 0),
        ]);
        $incidentDistribution = (clone $query)->join('incident_types', 'incident_types.id', '=', 'blotter_cases.incident_type_id')
            ->select('incident_types.name as label')->selectRaw('COUNT(*) AS total')
            ->groupBy('incident_types.id', 'incident_types.name')->orderByDesc('total')->get();
        $filteredIds = (clone $query)->select('blotter_cases.id');
        $partySitios = DB::table('case_complainants')->select('blotter_case_id', 'sitio')
            ->unionAll(DB::table('case_respondents')->select('blotter_case_id', 'sitio'));
        $sitioCounts = DB::query()->fromSub($partySitios, 'parties')
            ->joinSub(clone $filteredIds, 'cases', 'cases.id', '=', 'parties.blotter_case_id')
            ->whereIn('parties.sitio', $sitios)->select('parties.sitio')
            ->selectRaw('COUNT(DISTINCT parties.blotter_case_id) AS total')->groupBy('parties.sitio')->pluck('total', 'sitio');
        $sitioDistribution = collect($sitios)->map(fn ($sitio) => ['label' => $sitio, 'total' => (int) ($sitioCounts[$sitio] ?? 0)]);

        // Count only the current assignment of each open case, excluding former officers.
        $currentAssignments = (clone $query)->where('record_status', RecordStatus::Open->value)
            ->with('currentAssignment')->get(['blotter_cases.id']);
        $workloadCounts = $currentAssignments->filter(fn ($case) => $case->currentAssignment !== null)
            ->countBy(fn ($case) => $case->currentAssignment->assigned_to);
        $councilorWorkload = $councilors->map(fn ($officer) => [
            'label' => $officer->name, 'total' => (int) ($workloadCounts[$officer->id] ?? 0),
        ])->sortByDesc('total')->values();
        $unassignedCases = $currentAssignments->filter(fn ($case) => $case->currentAssignment === null)->count();

        $outcomeCounts = DB::table('mediation_sessions as sessions')
            ->join('mediation_outcomes as outcomes', 'outcomes.mediation_session_id', '=', 'sessions.id')
            ->joinSub(clone $filteredIds, 'cases', 'cases.id', '=', 'sessions.blotter_case_id')
            ->select('outcomes.outcome')->selectRaw('COUNT(DISTINCT sessions.blotter_case_id) AS total')
            ->groupBy('outcomes.outcome')->pluck('total', 'outcomes.outcome');
        $mediationDistribution = collect($mediationOutcomes)->map(fn ($outcome) => [
            'label' => $outcome, 'total' => (int) ($outcomeCounts[$outcome] ?? 0),
        ]);
        $outcomeDistribution = collect([
            ['label' => 'Resolved records', 'total' => $resolvedCases],
            ['label' => 'Dismissed', 'total' => $dismissedCases],
            ['label' => 'CFA / Referred', 'total' => $referredCases],
        ]);

        $openQuery = (clone $query)->where('record_status', RecordStatus::Open->value);
        $age = $this->daysBetween('blotter_cases.reported_at', '?');
        $validAgeQuery = (clone $openQuery)->whereNotNull('reported_at')->where('reported_at', '<=', $asOf);
        // Whole elapsed calendar days; future/missing report dates have no known age.
        $ageExpression = "FLOOR($age)";
        $agedCases = (clone $validAgeQuery)->select('blotter_cases.id', 'blotter_cases.case_stage')
            ->selectRaw("$ageExpression AS age_days", [$asOf->toDateTimeString()]);
        $ageBuckets = DB::query()->fromSub(clone $agedCases, 'aged')->selectRaw(
            "CASE WHEN age_days <= 7 THEN '0–7 days' WHEN age_days <= 14 THEN '8–14 days' "
            ."WHEN age_days <= 30 THEN '15–30 days' WHEN age_days <= 60 THEN '31–60 days' ELSE '61+ days' END AS bucket, COUNT(*) AS total"
        )->groupBy('bucket')->pluck('total', 'bucket');
        $knownAgeCases = (int) $ageBuckets->sum();
        $unknownAgeCases = $openCases - $knownAgeCases;
        $agingDistribution = collect(['0–7 days', '8–14 days', '15–30 days', '31–60 days', '61+ days'])
            ->map(fn ($bucket) => ['label' => $bucket, 'total' => (int) ($ageBuckets[$bucket] ?? 0)]);
        $agingDistribution->push(['label' => 'Date unavailable', 'total' => $unknownAgeCases]);
        $beyondTargetCases = $targetDays === null ? null : DB::query()->fromSub(clone $agedCases, 'aged')
            ->where('age_days', '>', $targetDays)->count();
        $backlogCounts = (clone $openQuery)->select('case_stage')->selectRaw('COUNT(*) AS total')
            ->groupBy('case_stage')->pluck('total', 'case_stage');
        $stageBacklog = collect(CaseStage::cases())->map(fn ($stage) => [
            'label' => $stage->label(), 'total' => (int) ($backlogCounts[$stage->value] ?? 0),
        ]);
        $oldestCases = (clone $validAgeQuery)->with('incidentType', 'currentAssignment.assignedOfficer')->withCasts(['age_days' => 'integer'])
            ->select('blotter_cases.*')->selectRaw("$ageExpression AS age_days", [$asOf->toDateTimeString()])
            ->orderBy('reported_at')->orderBy('blotter_cases.id')->limit(10)->get();
        $recentCases = (clone $query)->with('incidentType', 'currentAssignment.assignedOfficer')
            ->latest('reported_at')->orderByDesc('blotter_cases.id')->limit(10)->get();

        return compact('asOf', 'totalCases', 'openCases', 'resolvedCases', 'closedCases', 'dismissedCases',
            'referredCases', 'resolutionRate', 'avgResolutionDays', 'resolutionSamples', 'missingResolutionDates',
            'caseTrend', 'statusDistribution', 'stageDistribution', 'incidentDistribution', 'sitioDistribution',
            'councilorWorkload', 'unassignedCases', 'mediationDistribution', 'outcomeDistribution',
            'agingDistribution', 'unknownAgeCases', 'beyondTargetCases', 'stageBacklog', 'oldestCases', 'recentCases');
    }

    private function daysBetween(string $start, string $end): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "(julianday($end) - julianday($start))"
            : "(TIMESTAMPDIFF(SECOND, $start, $end) / 86400.0)";
    }
}
