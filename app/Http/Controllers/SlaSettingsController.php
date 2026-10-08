<?php

namespace App\Http\Controllers;

use App\Models\SlaSetting;
use App\Services\AuditLogService;
use App\Services\SlaSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SlaSettingsController extends Controller
{
    public function edit(SlaSettingsService $settings)
    {
        return view('settings.sla', ['policy' => $settings->policy(),
            'stages' => array_keys(config('analytics.sla_targets')), 'settings' => SlaSetting::with('updatedBy')->findOrFail(1)]);
    }

    public function update(Request $request)
    {
        $rawDates = $request->input('non_working_dates', '');
        $dates = is_string($rawDates) ? preg_split('/\r\n|\r|\n/', $rawDates) : [];
        $request->merge(['holiday_dates' => array_values(array_unique(array_filter(array_map('trim', $dates), fn ($date) => $date !== '')))]);
        $stages = array_keys(config('analytics.sla_targets'));
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'targets' => ['required', 'array', 'size:'.count($stages)],
            'targets.*' => ['required', 'array:days,unit'],
            'targets.*.days' => ['required', 'integer', 'min:1', 'max:365'],
            'targets.*.unit' => ['required', Rule::in(['working', 'calendar'])],
            'near_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'non_working_dates' => ['nullable', 'string', 'max:6000'],
            'holiday_dates' => ['array', 'max:500'],
            'holiday_dates.*' => ['required', 'date_format:Y-m-d'],
        ]);
        // Only the five fixed stage positions can be submitted.
        if (array_keys($data['targets']) !== range(0, count($stages) - 1)) {
            throw ValidationException::withMessages(['targets' => 'Submit one target for each workflow stage.']);
        }
        $targets = [];
        foreach ($stages as $index => $stage) {
            $targets[$stage] = ['days' => (int) $data['targets'][$index]['days'], 'unit' => $data['targets'][$index]['unit']];
        }
        sort($data['holiday_dates']);
        DB::transaction(function () use ($request, $data, $targets) {
            $settings = SlaSetting::lockForUpdate()->findOrFail(1);
            if ($settings->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Another user changed the SLA settings. Reload current settings and review your changes before saving.']);
            }
            $old = $settings->only(['targets', 'near_percent', 'non_working_dates', 'revision']);
            $settings->targets = $targets;
            $settings->near_percent = (int) $data['near_percent'];
            $settings->non_working_dates = $data['holiday_dates'];
            $settings->revision++;
            $settings->updated_by = $request->user()->id;
            $settings->save();
            AuditLogService::log('sla_settings_updated', 'Settings', 'Updated operational SLA targets, warning threshold, and non-working dates.',
                $settings, $old, $settings->only(['targets', 'near_percent', 'non_working_dates', 'revision']));
        });

        return redirect()->route('settings.sla.edit')->with('success', 'SLA settings saved. Open-case deadlines and indicators now use these settings.');
    }
}
