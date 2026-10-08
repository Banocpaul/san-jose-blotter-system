<?php

namespace App\Services;

use App\Models\BlotterCase;
use Carbon\CarbonImmutable;

class CaseSlaService
{
    public function evaluate(BlotterCase $case, CarbonImmutable $asOf): array
    {
        $target = config('analytics.sla_targets.'.$case->case_stage?->value);
        $start = $case->sla_started_at ? CarbonImmutable::instance($case->sla_started_at)->setTimezone('Asia/Manila') : null;
        $unknown = ['status' => 'Unavailable', 'due_at' => null, 'progress' => null, 'target' => $target];
        if (! $target || ! $start || $start > $asOf) {
            return $unknown;
        }
        $days = $target['days'] + (int) $case->sla_extension_days;
        $due = $start;
        for ($i = 0; $i < $days; $i++) {
            $due = $due->addDay();
            if ($target['unit'] === 'working') {
                while (! $this->isWorkingDate($due)) {
                    $due = $due->addDay();
                }
            }
        }
        $elapsed = $target['unit'] === 'working'
            ? $this->workingSeconds($start, $asOf->setTimezone('Asia/Manila'))
            : $asOf->getTimestamp() - $start->getTimestamp();
        $allowance = $target['unit'] === 'working'
            ? $this->workingSeconds($start, $due)
            : $due->getTimestamp() - $start->getTimestamp();
        $progress = $allowance > 0 ? $elapsed / $allowance : 0;

        return ['status' => $asOf > $due ? 'Beyond SLA' : ($progress >= .8 ? 'Near SLA' : 'Within SLA'),
            'due_at' => $due, 'progress' => $progress, 'target' => $target];
    }

    private function isWorkingDate(CarbonImmutable $date): bool
    {
        return $date->isWeekday() && ! in_array($date->toDateString(), config('analytics.non_working_dates', []), true);
    }

    private function workingSeconds(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($end <= $start) {
            return 0;
        }
        $seconds = 0;
        for ($day = $start->startOfDay(); $day < $end; $day = $day->addDay()) {
            if ($this->isWorkingDate($day)) {
                $seconds += max(0, min($day->addDay()->getTimestamp(), $end->getTimestamp())
                    - max($day->getTimestamp(), $start->getTimestamp()));
            }
        }

        return $seconds;
    }
}
