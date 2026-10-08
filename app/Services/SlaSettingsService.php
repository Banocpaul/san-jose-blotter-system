<?php

namespace App\Services;

use App\Models\SlaSetting;

class SlaSettingsService
{
    private ?array $loaded = null;

    public function policy(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }
        $settings = SlaSetting::findOrFail(1);
        $targets = [];
        foreach (config('analytics.sla_targets') as $stage => $default) {
            $targets[$stage] = ['days' => (int) ($settings->targets[$stage]['days'] ?? $default['days']),
                'unit' => $settings->targets[$stage]['unit'] ?? $default['unit'], 'start' => $default['start']];
        }

        return $this->loaded = ['targets' => $targets, 'near_percent' => $settings->near_percent,
            'non_working_dates' => $settings->non_working_dates, 'revision' => $settings->revision];
    }
}
