<?php

namespace Tests\Feature;

use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentAnalyticsTest extends TestCase
{
    private User $secretary;

    private IncidentType $incidentType;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
        // The historical default-only MODIFY statement is MySQL-specific.
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => ! str_contains($path, 'update_case_resolution_workflow')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true])->assertSuccessful();
        $role = Role::create(['name' => 'Secretary', 'slug' => 'secretary']);
        $this->secretary = User::create(['name' => 'Test Secretary', 'username' => 'secretary-test',
            'email' => 'secretary@example.test', 'password' => 'test-only', 'role_id' => $role->id, 'is_active' => true]);
        $this->incidentType = IncidentType::create(['code' => 'TEST', 'name' => 'Test Incident', 'is_active' => true]);
        $this->actingAs($this->secretary);
    }

    public function test_year_selector_defaults_to_latest_recorded_year_and_shows_twelve_months(): void
    {
        $this->case('2024-03-15');
        $this->case('2025-01-15');
        $this->case('2025-03-15');
        $this->case('2027-01-01');
        $this->case('2026-09-01', ['deleted_at' => now()]);
        $this->get('/incident-analytics')->assertOk()->assertViewHas('selectedYear', 2025)
            ->assertViewHas('totalIncidents', 2)->assertViewHas('availableYears', fn ($years) => $years->all() === [2025, 2024])
            ->assertViewHas('monthlyTrend', fn ($months) => $months->count() === 12
                && $months->pluck('total')->all() === [1, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0])
            ->assertSee('id="trend_year"', false)->assertSee('form="incident-filters"', false);
        $this->get('/incident-analytics?year=2024')->assertOk()->assertViewHas('totalIncidents', 1);
        $this->get('/incident-analytics?year=')->assertOk()->assertViewHas('selectedYear', null)->assertViewHas('totalIncidents', 3);
    }

    public function test_resolution_formula_excludes_dismissals_missing_dates_and_future_dates(): void
    {
        $this->case('2025-01-01', ['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved',
            'reported_at' => '2025-01-01 12:00:00', 'closed_at' => '2025-01-03 00:00:00']);
        $id = $this->case('2025-02-01', ['record_status' => 'Resolved', 'case_stage' => 'Settled/Resolved',
            'reported_at' => '2025-02-01 12:00:00']);
        DB::table('case_resolutions')->insert(['blotter_case_id' => $id, 'status' => 'Completed', 'resolved_at' => '2025-02-04 00:00:00']);
        $this->case('2025-03-01', ['record_status' => 'Resolved', 'reported_at' => null]);
        $this->case('2025-04-01', ['record_status' => 'Resolved', 'reported_at' => '2025-04-05 12:00:00', 'closed_at' => '2025-04-01 12:00:00']);
        $this->case('2025-05-01', ['record_status' => 'Resolved', 'closed_at' => '2027-01-01 12:00:00']);
        $this->case('2025-06-01', ['record_status' => 'Closed', 'case_stage' => 'Closed', 'closed_at' => '2025-07-01 12:00:00']);
        $this->get('/incident-analytics?year=2025')->assertOk()->assertViewHas('totalIncidents', 6)
            ->assertViewHas('resolutionRate', 83.3)->assertViewHas('avgResolutionDays', 2.0)
            ->assertViewHas('resolutionSamples', 2)->assertViewHas('resolutionByType', fn ($rows) => (int) $rows->first()->samples === 2);
    }

    public function test_repeats_use_registered_person_across_roles_and_years_without_double_counting(): void
    {
        $person = $this->person();
        $newPerson = $this->person();
        $prior = $this->case('2024-12-01');
        $this->party('case_complainants', $prior, $person);
        $repeat = $this->case('2025-01-01');
        $this->party('case_respondents', $repeat, $person);
        $this->party('case_complainants', $repeat, $person);
        $first = $this->case('2025-02-01');
        $this->party('case_respondents', $first, $newPerson);
        $unlinked = $this->case('2025-03-01');
        $this->party('case_complainants', $unlinked, null);
        $deletedPerson = $this->person();
        $deletedPrior = $this->case('2024-11-01', ['deleted_at' => now()]);
        $this->party('case_complainants', $deletedPrior, $deletedPerson);
        $otherFirst = $this->case('2025-04-01');
        $this->party('case_complainants', $otherFirst, $deletedPerson);
        $this->get('/incident-analytics?year=2025')->assertOk()->assertViewHas('totalIncidents', 4)
            ->assertViewHas('repeatIncidents', 1)->assertViewHas('linkedIncidents', 3)->assertViewHas('repeatIncidentRate', 33.3)
            ->assertSee('1 cases without registered participants are excluded');
    }

    public function test_repeat_history_uses_incident_time_and_counts_each_case_once(): void
    {
        $person = $this->person();
        foreach (['09:00:00', '11:00:00', '08:00:00'] as $time) {
            $id = $this->case('2025-06-01', ['incident_time' => $time]);
            $this->party('case_complainants', $id, $person);
            $this->party('case_respondents', $id, $person);
        }
        $this->get('/incident-analytics?year=2025')->assertOk()->assertViewHas('repeatIncidents', 2)
            ->assertViewHas('linkedIncidents', 3)->assertViewHas('repeatIncidentRate', 66.7);
    }

    public function test_selected_year_compares_same_dates_and_keeps_future_incidents_out(): void
    {
        $this->case('2025-02-01');
        $this->case('2025-11-01');
        $this->case('2026-02-01');
        $this->case('2026-03-01', ['record_status' => 'Resolved']);
        $this->case('2026-11-01');
        $this->get('/incident-analytics?year=2026')->assertOk()->assertViewHas('totalIncidents', 2)
            ->assertViewHas('previousMetrics', fn ($metrics) => $metrics['totalIncidents'] === 1)
            ->assertViewHas('comparisons', fn ($values) => $values['totalIncidents'] === 100.0 && $values['resolutionRate'] === 50.0)
            ->assertViewHas('previousEnd', fn ($end) => $end->toDateString() === '2025-10-08')
            ->assertSee('50.0 pp higher than previous period');
    }

    public function test_custom_date_range_comparison_and_filters_use_the_same_case_set(): void
    {
        $otherType = IncidentType::create(['code' => 'OTHER', 'name' => 'Other Type']);
        $this->case('2025-03-31');
        $this->case('2025-04-01');
        $this->case('2025-04-02');
        $this->case('2025-04-02', ['incident_type_id' => $otherType->id]);
        $this->case('2025-04-02', ['record_status' => 'Resolved']);
        $query = '/incident-analytics?year=2025&date_from=2025-04-01&date_to=2025-04-02&record_status=Open&incident_type_id='.$this->incidentType->id;
        $this->get($query)->assertOk()->assertViewHas('totalIncidents', 2)
            ->assertViewHas('previousStart', fn ($start) => $start->toDateString() === '2025-03-30')
            ->assertViewHas('previousEnd', fn ($end) => $end->toDateString() === '2025-03-31')
            ->assertViewHas('previousMetrics', fn ($metrics) => $metrics['totalIncidents'] === 1)
            ->assertViewHas('monthlyTrend', fn ($rows) => $rows->sum('total') === 2)
            ->assertViewHas('dayOfWeek', fn ($rows) => $rows->sum('total') === 2);
        $this->get('/incident-analytics?year=2025&date_to=2025-04-02')->assertOk();
    }

    public function test_outcome_categories_are_exclusive_and_sitio_counts_are_distinct(): void
    {
        foreach ([['Resolved', 'Settled/Resolved'], ['Open', 'For Mediation'], ['Closed', 'For Further Action/CFA'],
            ['Closed', 'Closed'], ['Open', 'New']] as [$status, $stage]) {
            $id = $this->case('2025-01-01', ['record_status' => $status, 'case_stage' => $stage]);
            $this->party('case_complainants', $id, null, 'Sitio 1');
            $this->party('case_respondents', $id, null, 'Sitio 1');
        }
        $this->get('/incident-analytics?year=2025')->assertOk()
            ->assertViewHas('outcomeDistribution', fn ($rows) => $rows->pluck('total')->all() === [1, 1, 1, 1, 1])
            ->assertViewHas('sitioDistribution', fn ($rows) => $rows->first()['total'] === 5)
            ->assertViewHas('dayOfWeek', fn ($rows) => $rows->count() === 7 && $rows->first()['label'] === 'Mon')
            ->assertSee('Dismissed');
    }

    public function test_missing_comparison_values_and_invalid_years_are_handled(): void
    {
        $this->get('/incident-analytics')->assertOk()->assertViewHas('totalIncidents', 0)
            ->assertViewHas('resolutionRate', null)->assertViewHas('repeatIncidentRate', null)
            ->assertViewHas('avgResolutionDays', null)->assertSee('No incidents match');
        foreach (['abc', 1899, 2101] as $year) {
            $this->get('/incident-analytics?year='.$year)->assertRedirect()->assertSessionHasErrors('year');
        }
        $this->get('/incident-analytics?date_from=2025-06-01&date_to=2025-01-01')->assertRedirect()->assertSessionHasErrors('date_to');
        $this->case('2025-01-01');
        $this->get('/incident-analytics?year=2025')->assertOk()->assertViewHas('comparisons', fn ($deltas) => $deltas['totalIncidents'] === null)
            ->assertSee('No comparable prior value');
    }

    private function case(string $date, array $values = []): int
    {
        return DB::table('blotter_cases')->insertGetId(array_merge([
            'reference_number' => 'INCIDENT-'.(++$this->sequence), 'incident_type_id' => $this->incidentType->id,
            'incident_date' => $date, 'incident_time' => '12:00:00', 'reported_at' => $date.' 12:00:00',
            'location' => 'Test location', 'narrative' => 'Test fixture', 'status' => 'Pending',
            'case_stage' => 'New', 'record_status' => 'Open', 'created_at' => now(), 'updated_at' => now(),
        ], $values));
    }

    private function person(): int
    {
        return DB::table('residents')->insertGetId(['resident_code' => 'PERSON-'.(++$this->sequence),
            'first_name' => 'Registered', 'last_name' => 'Person']);
    }

    private function party(string $table, int $caseId, ?int $personId, ?string $sitio = null): void
    {
        DB::table($table)->insert(['blotter_case_id' => $caseId, 'resident_id' => $personId,
            'first_name' => 'Test', 'last_name' => 'Person', 'sitio' => $sitio]);
    }
}
