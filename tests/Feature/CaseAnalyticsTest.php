<?php

namespace Tests\Feature;

use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseSlaService;
use App\Services\SystemBackupService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaseAnalyticsTest extends TestCase
{
    private User $secretary;

    private IncidentType $incidentType;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
        // Use the real schema migrations. The final historical migration changes
        // only a MySQL column default; its MODIFY syntax cannot run on SQLite.
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => ! str_contains($path, 'update_case_resolution_workflow')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true])->assertSuccessful();
        $this->secretary = $this->user('secretary');
        $this->incidentType = IncidentType::create(['code' => 'TEST', 'name' => 'Test Incident', 'is_active' => true]);
    }

    public function test_only_authorized_active_roles_can_access_all_tabs(): void
    {
        foreach (['barangay_captain', 'secretary', 'staff'] as $slug) {
            $user = $slug === 'secretary' ? $this->secretary : $this->user($slug);
            foreach (['operations', 'performance', 'bottlenecks'] as $tab) {
                $this->actingAs($user)->get('/analytics?tab='.$tab)->assertOk()
                    ->assertSee('Case Operations')->assertSee('Resolution &amp; Performance', false)
                    ->assertSee('SLA &amp; Bottlenecks', false);
            }
            if ($slug === 'staff') {
                $this->get('/analytics')->assertSee('Business Intelligence')->assertDontSee('Incident Analytics');
                $this->get('/incident-analytics')->assertForbidden();
            }
        }
        foreach (['councilor', 'lupon'] as $slug) {
            foreach (['operations', 'performance', 'bottlenecks'] as $tab) {
                $this->actingAs($this->user($slug))->get('/analytics?tab='.$tab)->assertForbidden();
            }
        }
        $inactive = $this->user('staff');
        $inactive->update(['is_active' => false]);
        $this->actingAs($inactive)->get('/analytics')->assertRedirect('/login');
        $this->get('/analytics')->assertRedirect('/login');
    }

    public function test_resolution_rate_and_duration_exclude_dismissals_and_invalid_dates(): void
    {
        $this->case(['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved', 'status' => 'Settled',
            'reported_at' => '2026-10-01 12:00:00', 'closed_at' => '2026-10-03 00:00:00']);
        $fallback = $this->case(['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved', 'status' => 'Resolved',
            'reported_at' => '2026-10-01 12:00:00', 'closed_at' => null]);
        DB::table('case_resolutions')->insert(['blotter_case_id' => $fallback->id, 'status' => 'Completed',
            'resolved_at' => '2026-10-04 00:00:00']);
        $this->case(['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved', 'closed_at' => null]);
        $this->case(['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved',
            'reported_at' => '2026-10-05 12:00:00', 'closed_at' => '2026-10-01 12:00:00']);
        $this->case(['record_status' => 'Closed', 'case_stage' => 'Closed', 'status' => 'Dismissed',
            'reported_at' => '2026-09-01 12:00:00', 'closed_at' => '2026-10-01 12:00:00']);
        $this->case(['record_status' => 'Closed', 'case_stage' => 'For Further Action/CFA', 'status' => 'Referred']);
        $this->case();
        $this->case(['record_status' => 'Resolved', 'deleted_at' => now()]);

        $this->actingAs($this->secretary)->get('/analytics?tab=performance')->assertOk()
            ->assertViewHas('totalCases', 7)->assertViewHas('resolvedCases', 4)->assertViewHas('dismissedCases', 1)
            ->assertViewHas('referredCases', 1)->assertViewHas('resolutionRate', 57.1)
            ->assertViewHas('avgResolutionDays', 2.0)->assertViewHas('resolutionSamples', 2)
            ->assertViewHas('missingResolutionDates', 2)->assertSee('Dismissed');
    }

    public function test_age_buckets_target_boundaries_and_oldest_open_cases(): void
    {
        foreach ([0, 7, 8, 14, 15, 30, 31, 60, 61] as $days) {
            $this->case(['reported_at' => now()->subDays($days)]);
        }
        $this->case(['reported_at' => now()->subDays(30)->subHours(23)]);
        $this->case(['reported_at' => null]);
        $this->case(['reported_at' => now()->addDay()]);
        $this->case(['record_status' => 'Resolved', 'reported_at' => now()->subDays(100)]);
        $this->actingAs($this->secretary)->get('/analytics?tab=bottlenecks&target_days=30')->assertOk()
            ->assertViewHas('openCases', 12)->assertViewHas('beyondTargetCases', 3)
            ->assertViewHas('unknownAgeCases', 2)->assertViewHas('agingDistribution', fn ($rows) => $rows->pluck('total')->all() === [2, 2, 3, 2, 1, 2])
            ->assertViewHas('oldestCases', fn ($cases) => $cases->first()->age_days === 61 && $cases->count() === 10);
        $this->get('/analytics?tab=bottlenecks')->assertOk()->assertViewHas('beyondTargetCases', null)->assertSee('Not set');
    }

    public function test_all_filters_share_one_case_set_and_do_not_duplicate_related_rows(): void
    {
        $officer = $this->user('councilor');
        $former = $this->user('councilor');
        $matching = $this->case(['incident_date' => '2026-09-01', 'case_stage' => 'Under Assessment']);
        $other = $this->case(['incident_date' => '2026-08-01']);
        foreach (['case_complainants', 'case_respondents'] as $table) {
            DB::table($table)->insert(['blotter_case_id' => $matching->id, 'first_name' => 'Sample',
                'last_name' => 'Person', 'sitio' => 'Sitio 1']);
        }
        DB::table('case_assignments')->insert([
            ['blotter_case_id' => $matching->id, 'assigned_to' => $former->id, 'assigned_by' => $this->secretary->id,
                'assigned_at' => now()->subDays(7), 'completed_at' => now()->subDays(2)],
            ['blotter_case_id' => $matching->id, 'assigned_to' => $officer->id, 'assigned_by' => $this->secretary->id,
                'assigned_at' => now()->subDays(2), 'completed_at' => null],
            ['blotter_case_id' => $other->id, 'assigned_to' => $officer->id, 'assigned_by' => $this->secretary->id,
                'assigned_at' => now()->subDays(2), 'completed_at' => null],
        ]);
        foreach ([1, 2] as $hearing) {
            $session = DB::table('mediation_sessions')->insertGetId(['blotter_case_id' => $matching->id,
                'hearing_number' => $hearing, 'scheduled_date' => '2026-09-01', 'created_by' => $this->secretary->id]);
            DB::table('mediation_outcomes')->insert(['mediation_session_id' => $session, 'outcome' => 'Rescheduled',
                'recorded_by' => $this->secretary->id, 'recorded_at' => now()]);
        }
        $params = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'case_stage' => 'Under Assessment',
            'record_status' => 'Open', 'incident_type_id' => $this->incidentType->id, 'sitio' => 'Sitio 1',
            'councilor_id' => $officer->id, 'mediation_outcome' => 'Rescheduled', 'target_days' => 5];
        foreach (['operations', 'performance', 'bottlenecks'] as $tab) {
            $this->actingAs($this->secretary)->get('/analytics?'.http_build_query($params + ['tab' => $tab]))->assertOk()
                ->assertViewHas('totalCases', 1)->assertViewHas('openCases', 1)
                ->assertViewHas('sitioDistribution', fn ($rows) => $rows->first()['total'] === 1)
                ->assertViewHas('councilorWorkload', fn ($rows) => $rows->sum('total') === 1)
                ->assertViewHas('mediationDistribution', fn ($rows) => $rows->sum('total') === 1)
                ->assertViewHas('recentCases', fn ($cases) => $cases->pluck('id')->all() === [$matching->id]);
        }
        $this->get('/analytics?councilor_id='.$former->id)->assertOk()->assertViewHas('totalCases', 0);
    }

    public function test_empty_results_missing_dates_and_invalid_filters_are_handled(): void
    {
        $this->actingAs($this->secretary)->get('/analytics?tab=performance')->assertOk()
            ->assertViewHas('resolutionRate', null)->assertViewHas('avgResolutionDays', null)
            ->assertSee('No cases match the selected filters.');
        foreach ([['target_days' => 0], ['target_days' => 3651], ['target_days' => 'abc'], ['tab' => 'invalid'],
            ['case_stage' => 'Invalid'], ['record_status' => 'Invalid'], ['sitio' => 'Sitio 5'],
            ['date_from' => '2026-10-08', 'date_to' => '2026-01-01'], ['councilor_id' => $this->secretary->id]] as $invalid) {
            $this->get('/analytics?'.http_build_query($invalid))->assertRedirect()->assertSessionHasErrors(array_keys($invalid)[0] === 'date_from' ? 'date_to' : array_keys($invalid)[0]);
        }
        $this->get('/analytics?date_to=2026-10-08')->assertOk();
        $this->get('/analytics?date_from=2026-01-01')->assertOk();
        $this->case(['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved', 'reported_at' => null]);
        $this->get('/analytics?tab=performance')->assertOk()->assertViewHas('avgResolutionDays', null)
            ->assertViewHas('missingResolutionDates', 1);
    }

    public function test_trend_includes_months_with_zero_cases_and_labels_dismissed_stage(): void
    {
        $this->case(['incident_date' => '2026-01-15', 'case_stage' => 'Closed', 'record_status' => 'Closed']);
        $this->case(['incident_date' => '2026-03-15']);
        $this->actingAs($this->secretary)->get('/analytics')->assertOk()
            ->assertViewHas('caseTrend', fn ($rows) => $rows->pluck('total')->all() === [1, 0, 1])
            ->assertSee('value="Closed"', false)->assertSee('Dismissed')
            ->assertSee('data-type="bar"', false)
            ->assertSee('data-type="doughnut"', false);
    }

    public function test_stage_tracking_records_real_transitions_and_does_not_guess_legacy_dates(): void
    {
        $legacy = $this->case(['reported_at' => now()->subDays(100), 'case_stage' => 'For Mediation']);
        $this->assertNull($legacy->stage_entered_at);
        $this->assertNull($legacy->sla_started_at);
        $legacy->update(['remarks' => 'Routine edit']);
        $this->assertNull($legacy->fresh()->stage_entered_at);
        $case = new BlotterCase(['incident_type_id' => $this->incidentType->id, 'incident_date' => '2026-10-08',
            'location' => 'Test location', 'narrative' => 'Actual new filing']);
        $case->reference_number = 'TRACKED-NEW';
        $case->save();
        $this->assertTrue($case->stage_entered_at->equalTo(now()));
        $this->assertTrue($case->tracked_opened_at->equalTo(now()));
        $this->assertTrue($case->sla_started_at->equalTo($case->reported_at));
        $case->sla_extension_days = 15;
        $case->sla_extension_reason = 'Old allowance';
        $this->travel(1)->days();
        $case->update(['case_stage' => 'For Mediation']);
        $this->assertTrue($case->fresh()->stage_entered_at->equalTo(now()));
        $this->assertNull($case->fresh()->sla_started_at);
        $this->assertSame(0, $case->fresh()->sla_extension_days);
        $case->update(['record_status' => 'Closed']);
        $this->travel(1)->days();
        $case->update(['record_status' => 'Open']);
        $this->assertTrue($case->fresh()->stage_entered_at->equalTo(now()));
        $this->assertNull($case->fresh()->sla_started_at);
    }

    public function test_sla_buckets_are_exclusive_and_use_stage_clocks_and_known_stage_age(): void
    {
        // 15-day calendar clock: 80% starts at 12 days, exact deadline is Near.
        foreach ([1, 12, 15, 16] as $days) {
            $this->case(['case_stage' => 'For Mediation', 'stage_entered_at' => now()->subDays(20),
                'sla_started_at' => now()->subDays($days)]);
        }
        $legacy = $this->case(['case_stage' => 'For Mediation']);
        $this->case(['case_stage' => 'For Mediation', 'sla_started_at' => now()->addDay()]);
        $this->actingAs($this->secretary)->get('/analytics?tab=bottlenecks')->assertOk()
            ->assertViewHas('withinSla', 1)->assertViewHas('nearSla', 2)->assertViewHas('beyondSla', 1)
            ->assertViewHas('unknownSla', 2)->assertViewHas('avgCurrentStageDays', 20.0)
            ->assertViewHas('currentStageSamples', 4)->assertViewHas('bottleneckCases', fn ($cases) => $cases->count() === 3)
            ->assertViewHas('untrackedCases', fn ($cases) => $cases->contains('id', $legacy->id))
            ->assertViewHas('slaStageRows', fn ($rows) => $rows->sum(fn ($r) => $r['Within SLA'] + $r['Near SLA'] + $r['Beyond SLA'] + $r['Unavailable']) === 6);
    }

    public function test_working_day_deadlines_skip_weekends_and_configured_holidays(): void
    {
        config(['analytics.non_working_dates' => ['2026-10-12']]);
        $case = $this->case(['case_stage' => 'New', 'sla_started_at' => '2026-10-09 01:00:00']); // Friday 9AM PHT
        $clock = app(CaseSlaService::class);
        $atWeekend = CarbonImmutable::parse('2026-10-11 09:00:00', 'Asia/Manila');
        $result = $clock->evaluate($case, $atWeekend);
        $this->assertSame('2026-10-13 09:00', $result['due_at']->format('Y-m-d H:i'));
        $this->assertSame('Within SLA', $result['status']);
        $this->assertSame('Near SLA', $clock->evaluate($case, CarbonImmutable::parse('2026-10-13 09:00:00', 'Asia/Manila'))['status']);
        $this->assertSame('Beyond SLA', $clock->evaluate($case, CarbonImmutable::parse('2026-10-13 09:00:01', 'Asia/Manila'))['status']);
    }

    public function test_confirmed_sla_starts_and_pangkat_extensions_require_authorized_role_and_are_audited(): void
    {
        $case = $this->case(['case_stage' => 'For Pangkat/Conciliation', 'reported_at' => '2026-09-01 00:00:00']);
        $params = ['case_id' => $case->id, 'started_at' => '2026-09-20T09:00', 'reason' => 'Confirmed first meeting in signed minutes.'];
        $this->actingAs($this->user('staff'))->post('/analytics/sla/start', $params)->assertForbidden();
        $this->post('/analytics/sla/extension', $params)->assertForbidden();
        $this->actingAs($this->secretary)->post('/analytics/sla/start', $params + ['ignore' => 'unused'])->assertRedirect();
        $this->assertSame('2026-09-20 01:00:00', $case->fresh()->sla_started_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'sla_start_recorded')->count());
        $this->post('/analytics/sla/start', $params)->assertSessionHasErrors('case_id');
        $this->get('/analytics?tab=bottlenecks')->assertOk()->assertViewHas('beyondSla', 1);
        $this->post('/analytics/sla/extension', ['case_id' => $case->id, 'reason' => 'Pangkat approved extension in signed minutes.'])->assertRedirect();
        $this->assertSame(15, $case->fresh()->sla_extension_days);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'sla_extension_recorded')->count());
        $this->get('/analytics?tab=bottlenecks')->assertOk()->assertViewHas('beyondSla', 0)->assertViewHas('withinSla', 1);
        $this->post('/analytics/sla/extension', ['case_id' => $case->id, 'reason' => 'Attempting to duplicate an approved extension.'])->assertSessionHasErrors('case_id');
        $mediation = $this->case(['case_stage' => 'For Mediation']);
        $this->post('/analytics/sla/start', ['case_id' => $mediation->id, 'started_at' => '2027-01-01T09:00', 'reason' => 'Actual date not yet known.'])->assertSessionHasErrors('started_at');
        $this->post('/analytics/sla/start', ['case_id' => $mediation->id, 'started_at' => '2026-09-01T09:00', 'reason' => 'Date before case was reported.'])->assertSessionHasErrors('started_at');
        $this->post('/analytics/sla/extension', ['case_id' => $mediation->id, 'reason' => 'Extension for a mediation case is not allowed.'])->assertSessionHasErrors('case_id');
    }

    public function test_reporting_year_uses_filing_and_completion_dates_and_conversion_uses_confirmed_openings(): void
    {
        $this->case(['reported_at' => '2025-12-31 10:00:00', 'closed_at' => '2026-01-02 10:00:00',
            'record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved', 'tracked_opened_at' => '2025-12-31 10:00:00']);
        $this->case(['reported_at' => '2026-01-01 10:00:00', 'closed_at' => '2026-01-11 10:00:00',
            'record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved', 'tracked_opened_at' => '2026-01-01 10:00:00']);
        $this->case(['reported_at' => '2026-01-03 10:00:00', 'closed_at' => '2026-01-04 10:00:00',
            'record_status' => 'Closed', 'case_stage' => 'Closed', 'tracked_opened_at' => '2026-01-03 10:00:00']);
        $this->case(['reported_at' => '2026-01-04 10:00:00', 'closed_at' => '2026-01-05 10:00:00',
            'record_status' => 'Closed', 'case_stage' => 'For Further Action/CFA']);
        $this->case(['reported_at' => '2026-01-05 10:00:00', 'closed_at' => null,
            'record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved']);
        $this->case(['reported_at' => '2026-01-01 10:00:00']);
        $this->case(['reported_at' => now()->addDay(), 'closed_at' => now()->addDays(2),
            'record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved']);
        $this->actingAs($this->secretary)->get('/analytics?tab=performance&year=2026')->assertOk()
            ->assertViewHas('resolvedThisPeriod', 2)->assertViewHas('closedThisPeriod', 2)
            ->assertViewHas('filedThisPeriod', 5)->assertViewHas('periodResolutionRate', 40.0)
            ->assertViewHas('conversionSamples', 2)->assertViewHas('convertedCases', 1)->assertViewHas('conversionRate', 50.0)
            ->assertViewHas('periodAverageResolution', 6.0)
            ->assertViewHas('performanceSummary', fn ($rows) => $rows->count() === 12 && $rows->first()['resolved'] === 2 && $rows->first()['filed'] === 5)
            ->assertViewHas('periodOutcomes', fn ($rows) => $rows->pluck('total')->all() === [2, 0, 1, 1])
            ->assertViewHas('processingDistribution', fn ($rows) => $rows->pluck('total')->all() === [1, 1, 0, 0])
            ->assertSee('data-type="line"', false)->assertSee('data-stacked="true"', false);
        $this->get('/analytics?tab=performance&year=2025')->assertOk()->assertViewHas('resolvedThisPeriod', 0)
            ->assertViewHas('filedThisPeriod', 1)->assertViewHas('periodResolutionRate', 100.0);
        $this->get('/analytics?year=all')->assertOk()->assertViewHas('reportingYear', 'all');
        $this->get('/analytics?year=bad')->assertSessionHasErrors('year');
    }

    public function test_pending_schedule_counts_keep_overdue_hearings_and_exclude_closed_cases(): void
    {
        $active = $this->case(['case_stage' => 'For Mediation']);
        $closed = $this->case(['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved']);
        foreach ([[$active->id, 1, '2026-10-07', 'Scheduled'], [$active->id, 2, '2026-10-09', 'Scheduled'],
            [$active->id, 3, '2026-10-10', 'Completed'], [$closed->id, 1, '2026-10-09', 'Scheduled']] as [$id, $number, $date, $status]) {
            DB::table('mediation_sessions')->insert(['blotter_case_id' => $id, 'hearing_number' => $number,
                'scheduled_date' => $date, 'scheduled_time' => '10:00:00', 'status' => $status, 'created_by' => $this->secretary->id]);
        }
        $this->actingAs($this->secretary)->get('/analytics')->assertOk()
            ->assertViewHas('pendingHearings', fn ($rows) => $rows->count() === 2 && $rows->first()->dashboard_overdue === true)
            ->assertViewHas('activeSnapshot', fn ($rows) => $rows->first()->dashboardNextSession->scheduled_date->format('Y-m-d') === '2026-10-09')
            ->assertViewHas('unknownSla', 1)->assertSee('Past schedule');
    }

    public function test_previous_schema_backups_keep_unknown_timing_and_other_missing_columns_are_rejected(): void
    {
        $this->user('barangay_captain');
        $legacy = $this->case(['case_stage' => 'For Mediation']);
        $service = app(SystemBackupService::class);
        $payload = $service->createPayload();
        $doc = $service->decodePayload($payload);
        $fields = ['stage_entered_at', 'tracked_opened_at', 'sla_started_at', 'sla_extension_days', 'sla_extension_reason'];
        $doc['schema']['blotter_cases'] = array_values(array_diff($doc['schema']['blotter_cases'], $fields));
        foreach ($doc['tables']['blotter_cases'] as &$row) {
            $row = array_diff_key($row, array_flip($fields));
        }
        unset($row);
        $validate = new \ReflectionMethod($service, 'validateDocument');
        $validate->invoke($service, $doc);
        $service->restore($doc);
        $this->assertNull($legacy->fresh()->stage_entered_at);
        $this->assertNull($legacy->fresh()->sla_started_at);
        $this->assertSame(0, $legacy->fresh()->sla_extension_days);
        $doc['schema']['blotter_cases'] = array_values(array_diff($doc['schema']['blotter_cases'], ['reported_at']));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backup schema mismatch');
        $validate->invoke($service, $doc);
    }

    private function user(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);
        $number = ++$this->sequence;

        return User::create(['name' => 'Test '.$slug.' '.$number, 'username' => 'test'.$number,
            'email' => 'test'.$number.'@example.test', 'password' => 'test-only-password', 'role_id' => $role->id, 'is_active' => true]);
    }

    private function case(array $attributes = []): BlotterCase
    {
        $id = DB::table('blotter_cases')->insertGetId(array_merge([
            'reference_number' => 'TEST-'.(++$this->sequence), 'incident_type_id' => $this->incidentType->id,
            'incident_date' => '2026-10-01', 'location' => 'Test location', 'narrative' => 'Test fixture',
            'status' => 'Pending', 'case_stage' => 'New', 'record_status' => 'Open',
            'reported_at' => '2026-10-01 12:00:00', 'created_at' => now(), 'updated_at' => now(),
        ], $attributes));

        return BlotterCase::withTrashed()->findOrFail($id);
    }
}
