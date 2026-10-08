<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BusinessIntelligenceService
{
    public function summarize(Builder $query, array $filters, CarbonImmutable $asOf): array
    {
        // Load only dashboard fields. Related schedules/actions are loaded in batches.
        $cases = (clone $query)->with(['incidentType', 'caseResolution',
            'mediationSessions' => fn ($q) => $q->with('outcome')->orderBy('scheduled_date')->orderBy('scheduled_time'),
            'investigationNotes' => fn ($q) => $q->orderByDesc('noted_at'),
        ])->get();
        $open = $cases->where('record_status', RecordStatus::Open)->values();
        $clock = app(CaseSlaService::class);
        foreach ($open as $case) {
            $actions = $case->investigationNotes->filter(fn ($n) => $n->noted_at !== null && $n->noted_at <= $asOf)
                ->map(fn ($n) => ['text' => $n->action_taken ?: $n->note, 'date' => $n->noted_at]);
            foreach ($case->mediationSessions as $session) {
                if ($session->outcome?->recorded_at !== null && $session->outcome->recorded_at <= $asOf) {
                    $actions->push(['text' => ($session->proceeding_type ?: 'Mediation').' · '.$session->outcome->outcome,
                        'date' => $session->outcome->recorded_at]);
                }
            }
            $last = $actions->sortByDesc(fn ($a) => $a['date']->getTimestamp())->first();
            if ($last) {
                $last['text'] = Str::limit($last['text'], 100);
            }
            $case->setAttribute('dashboard_last_action', $last);
            $case->setAttribute('dashboard_sla', $clock->evaluate($case, $asOf));
            $case->setAttribute('dashboard_stage_days', $this->duration($case->stage_entered_at, $asOf, $asOf));
            $case->setAttribute('dashboard_age_days', $this->duration($case->reported_at, $asOf, $asOf));
            $next = $case->mediationSessions->first(fn ($s) => $s->status === 'Scheduled' && $this->scheduleTime($s) >= $asOf);
            $case->setRelation('dashboardNextSession', $next);
        }
        $pendingHearings = $open->flatMap(fn ($case) => $case->mediationSessions
            ->filter(fn ($s) => $s->status === 'Scheduled')
            ->map(function ($s) use ($case, $asOf) {
                $s->setRelation('blotterCase', $case);
                $s->setAttribute('dashboard_scheduled_at', $this->scheduleTime($s));
                $s->setAttribute('dashboard_overdue', $this->scheduleTime($s) < $asOf);

                return $s;
            }))->sortBy(fn ($s) => $s->dashboard_scheduled_at->getTimestamp())->values();
        $stageSummary = collect(CaseStage::kpiStages())->map(function ($stage) use ($open) {
            $rows = $open->where('case_stage', $stage);
            $samples = $rows->pluck('dashboard_stage_days')->filter(fn ($v) => $v !== null);
            $states = $rows->countBy(fn ($case) => $case->dashboard_sla['status']);

            return ['label' => $stage->label(), 'total' => $rows->count(), 'stage' => $stage->value,
                'avg_days' => $samples->isEmpty() ? null : round($samples->avg(), 1), 'samples' => $samples->count(),
                'within' => $states['Within SLA'] ?? 0, 'near' => $states['Near SLA'] ?? 0,
                'beyond' => $states['Beyond SLA'] ?? 0, 'unknown' => $states['Unavailable'] ?? 0];
        });
        $stageSamples = $open->pluck('dashboard_stage_days')->filter(fn ($v) => $v !== null);
        $avgCurrentStageDays = $stageSamples->isEmpty() ? null : round($stageSamples->avg(), 1);
        $currentStageSamples = $stageSamples->count();
        $slaCounts = $open->countBy(fn ($case) => $case->dashboard_sla['status']);
        $withinSla = $slaCounts['Within SLA'] ?? 0;
        $nearSla = $slaCounts['Near SLA'] ?? 0;
        $beyondSla = $slaCounts['Beyond SLA'] ?? 0;
        $unknownSla = $slaCounts['Unavailable'] ?? 0;
        $highestDelay = $stageSummary->whereNotNull('avg_days')->sortByDesc('avg_days')->first();
        $longestPending = $open->filter(fn ($case) => $case->dashboard_age_days !== null)
            ->sortByDesc('dashboard_age_days')->first();
        $activeSnapshot = $open->sortByDesc(fn ($c) => $c->dashboard_stage_days ?? -1)->take(5)->values();
        $bottleneckCases = $open->filter(fn ($c) => in_array($c->dashboard_sla['status'], ['Near SLA', 'Beyond SLA'], true))
            ->sortByDesc(fn ($c) => $c->dashboard_age_days ?? -1)->take(10)->values();
        $untrackedCases = $open->where('dashboard_sla.status', 'Unavailable')->take(5)->values();
        $clockCandidates = $open->filter(fn ($c) => $c->sla_started_at === null && in_array($c->case_stage,
            [CaseStage::ForMediation, CaseStage::ForPangkatConciliation, CaseStage::ForFurtherActionCfa], true))->values();
        $extensionCandidates = $open->filter(fn ($c) => $c->case_stage === CaseStage::ForPangkatConciliation
            && $c->sla_started_at !== null && ! $c->sla_extension_days)->values();

        // Filing period is explicit; incident-date cohort filters remain separate.
        $localNow = $asOf->setTimezone('Asia/Manila');
        $year = ($filters['year'] ?? $localNow->year) !== 'all' ? (int) ($filters['year'] ?? $localNow->year) : null;
        $dates = $cases->flatMap(fn ($c) => [$c->reported_at, $c->closed_at, $c->caseResolution?->resolved_at])->filter()->map(fn ($d) => CarbonImmutable::instance($d)->setTimezone('Asia/Manila'))
            ->filter(fn ($d) => $d <= $localNow);
        $availableYears = $dates->map(fn ($d) => $d->year)->push($localNow->year)->when($year !== null, fn ($years) => $years->push($year))->unique()->sortDesc()->values();
        $reportingYear = $year ?? 'all';
        $periodStart = $year ? CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Manila')
            : ($dates->min()?->startOfMonth() ?? $localNow->startOfMonth());
        $periodEnd = $year ? $periodStart->endOfYear() : $localNow;
        $periodEnd = $periodEnd->min($localNow);
        $periodLabel = $year ? ($year === $localNow->year ? $year.' · year to date' : (string) $year) : 'All recorded filing dates';
        $months = collect();
        if ($year || $periodStart <= $periodEnd) {
            for ($month = $periodStart->startOfMonth(); $month <= ($year ? $periodStart->endOfYear() : $periodEnd); $month = $month->addMonth()) {
                $months->put($month->format('Y-m'), ['label' => $month->format($year ? 'M' : 'M Y'), 'period' => $month->format('Y-m'),
                    'filed' => 0, 'resolved' => 0, 'closed' => 0, 'referred' => 0, 'dismissed' => 0, 'durations' => [], 'cohort_resolved' => 0]);
            }
        }
        $resolutionDurations = collect();
        $resolvedByType = collect();
        $resolvedThisPeriod = 0;
        $closedThisPeriod = 0;
        $outcomes = ['Settled / Resolved' => 0, 'Other Closed' => 0, 'CFA / Referred' => 0, 'Dismissed' => 0];
        $openedCohort = $cases->filter(fn ($c) => $c->tracked_opened_at !== null
            && $this->inPeriod($c->tracked_opened_at, $periodStart, $periodEnd));
        $conversionSamples = $openedCohort->count();
        $convertedCases = $openedCohort->where('record_status', RecordStatus::Resolved)->count();
        $conversionRate = $conversionSamples ? round($convertedCases / $conversionSamples * 100, 1) : null;
        $filedThisPeriod = 0;
        $filedResolved = 0;
        foreach ($cases as $case) {
            $filed = $this->inPeriod($case->reported_at, $periodStart, $periodEnd);
            if ($filed) {
                $filedThisPeriod++;
                $key = CarbonImmutable::instance($case->reported_at)->setTimezone('Asia/Manila')->format('Y-m');
                $row = $months->get($key);
                if ($row !== null) {
                    $row['filed']++;
                    if ($case->record_status === RecordStatus::Resolved) {
                        $row['cohort_resolved']++;
                        $filedResolved++;
                    }
                    $months->put($key, $row);
                }
            }
            if ($case->record_status === RecordStatus::Open) {
                continue;
            }
            $end = $case->closed_at ?? ($case->record_status === RecordStatus::Resolved && $case->caseResolution?->status === 'Completed' ? $case->caseResolution->resolved_at : null);
            if (! $this->inPeriod($end, $periodStart, $periodEnd) || ($case->reported_at !== null && $end < $case->reported_at)) {
                continue;
            }
            $resolved = $case->record_status === RecordStatus::Resolved;
            $category = $resolved ? 'Settled / Resolved' : match ($case->case_stage) {
                CaseStage::ForFurtherActionCfa => 'CFA / Referred',
                CaseStage::Closed => 'Dismissed',
                default => 'Other Closed',
            };
            $outcomes[$category]++;
            $resolved ? $resolvedThisPeriod++ : $closedThisPeriod++;
            $key = CarbonImmutable::instance($end)->setTimezone('Asia/Manila')->format('Y-m');
            $row = $months->get($key);
            $duration = $resolved ? $this->duration($case->reported_at, $end, $asOf) : null;
            if ($row !== null) {
                $column = $resolved ? 'resolved' : match ($category) {
                    'CFA / Referred' => 'referred', 'Dismissed' => 'dismissed', default => 'closed'
                };
                $row[$column]++;
                if ($duration !== null) {
                    $row['durations'][] = $duration;
                }
                $months->put($key, $row);
            }
            if ($duration !== null) {
                $resolutionDurations->push($duration);
                $type = $case->incidentType?->name ?? 'Unknown';
                $resolvedByType->put($type, [...$resolvedByType->get($type, []), $duration]);
            }
        }
        $periodResolutionRate = $filedThisPeriod ? round($filedResolved / $filedThisPeriod * 100, 1) : null;
        $periodAverageResolution = $resolutionDurations->isEmpty() ? null : round($resolutionDurations->avg(), 1);
        $resolutionTimeByType = $resolvedByType->map(fn ($values, $label) => ['label' => $label, 'total' => round(collect($values)->avg(), 1), 'samples' => count($values)])->values();
        $processingBuckets = collect(['≤ 5 days' => 0, '> 5–10 days' => 0, '> 10–15 days' => 0, '> 15 days' => 0]);
        foreach ($resolutionDurations as $days) {
            $bucket = $days <= 5 ? '≤ 5 days' : ($days <= 10 ? '> 5–10 days' : ($days <= 15 ? '> 10–15 days' : '> 15 days'));
            $processingBuckets->put($bucket, $processingBuckets->get($bucket) + 1);
        }
        $processingDistribution = $processingBuckets->map(fn ($total, $label) => compact('label', 'total'))->values();
        $performanceSummary = $months->map(fn ($row) => $row + [
            'resolution_rate' => $row['filed'] ? round($row['cohort_resolved'] / $row['filed'] * 100, 1) : null,
            'avg_days' => count($row['durations']) ? round(collect($row['durations'])->avg(), 1) : null,
        ])->values();
        $filedTrend = $months->map(fn ($r) => ['label' => $r['label'], 'total' => $r['filed']])->values();
        $resolutionTrend = $months->map(fn ($r) => ['label' => $r['label'], 'total' => $r['resolved']])->values();
        $periodOutcomes = collect($outcomes)->map(fn ($total, $label) => compact('label', 'total'))->values();
        $openResolvedRows = $months->map(fn ($r) => ['label' => $r['label'], 'Opened / Filed' => $r['filed'], 'Resolved' => $r['resolved']])->values();
        $monthlyOutcomeRows = $months->map(fn ($r) => ['label' => $r['label'], 'Settled / Resolved' => $r['resolved'],
            'Other Closed' => $r['closed'], 'CFA / Referred' => $r['referred'], 'Dismissed' => $r['dismissed']])->values();
        $slaStageRows = $stageSummary->map(fn ($r) => ['label' => $r['label'], 'Within SLA' => $r['within'], 'Near SLA' => $r['near'],
            'Beyond SLA' => $r['beyond'], 'Unavailable' => $r['unknown']]);
        $stageAverageRows = $stageSummary->map(fn ($r) => ['label' => $r['label'], 'total' => $r['avg_days']]);
        $delayedStageRows = $stageSummary->map(fn ($r) => ['label' => $r['label'], 'total' => $r['beyond']]);

        return compact('open', 'pendingHearings', 'stageSummary', 'avgCurrentStageDays', 'currentStageSamples',
            'withinSla', 'nearSla', 'beyondSla', 'unknownSla', 'highestDelay', 'longestPending', 'activeSnapshot',
            'availableYears', 'reportingYear', 'bottleneckCases', 'untrackedCases', 'clockCandidates', 'extensionCandidates', 'periodLabel',
            'resolvedThisPeriod', 'closedThisPeriod', 'conversionSamples', 'convertedCases', 'conversionRate',
            'filedThisPeriod', 'periodResolutionRate', 'periodAverageResolution', 'resolutionDurations',
            'resolutionTimeByType', 'processingDistribution', 'performanceSummary', 'filedTrend', 'resolutionTrend',
            'periodOutcomes', 'openResolvedRows', 'monthlyOutcomeRows', 'slaStageRows', 'stageAverageRows', 'delayedStageRows');
    }

    private function duration($start, $end, CarbonImmutable $asOf): ?float
    {
        if ($start === null || $end === null) {
            return null;
        }
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);
        if ($end < $start || $end > $asOf) {
            return null;
        }

        return ($end->getTimestamp() - $start->getTimestamp()) / 86400;
    }

    private function inPeriod($date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $date !== null && $date >= $start && $date <= $end;
    }

    private function scheduleTime($session): CarbonImmutable
    {
        // Schedules are entered in local barangay time; a date without time is
        // treated as end-of-day so it does not become overdue at midnight.
        return CarbonImmutable::parse($session->scheduled_date->format('Y-m-d').' '.($session->scheduled_time ?: '23:59:59'), 'Asia/Manila');
    }
}
