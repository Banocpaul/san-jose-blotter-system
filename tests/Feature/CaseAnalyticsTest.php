<?php

namespace Tests\Feature;

use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
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
            ->assertSee('data-type="bar"', false)->assertSee('data-type="line"', false)
            ->assertSee('data-type="doughnut"', false);
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
