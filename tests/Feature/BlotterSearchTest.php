<?php

namespace Tests\Feature;

use App\Enums\CaseStage;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlotterSearchTest extends TestCase
{
    private IncidentType $incidentType;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => ! str_contains($path, 'update_case_resolution_workflow')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true])->assertSuccessful();
        $this->incidentType = IncidentType::create(['code' => 'TEST', 'name' => 'Noise Complaint', 'is_active' => true]);
        $this->actingAs($this->user('secretary'));
    }

    public function test_full_names_match_across_name_fields_in_the_list_and_dropdown(): void
    {
        $id = $this->record();
        DB::table('case_complainants')->insert(['blotter_case_id' => $id,
            'first_name' => 'Paul Randolf', 'last_name' => 'Bañoc', 'suffix' => 'Jr.']);
        foreach (['Paul Randolf Bañoc', 'Bañoc Paul', 'Paul   Bañoc Jr.'] as $term) {
            $url = '/blotter?search='.urlencode($term);
            $this->get($url)->assertOk()->assertViewHas('cases', fn ($cases) => $cases->modelKeys() === [$id]);
            $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.complainants', 'Paul Randolf Bañoc Jr.')
                ->assertJsonPath('data.0.view_url', route('blotter.show', $id));
        }
        $this->get('/blotter')->assertSee('data-blotter-search-input', false)
            ->assertSee('autocomplete="off"', false);
    }

    public function test_respondents_match_full_names_without_combining_different_people(): void
    {
        $id = $this->record();
        DB::table('case_respondents')->insert(['blotter_case_id' => $id,
            'first_name' => 'Pedro', 'middle_name' => 'Santos', 'last_name' => 'Cruz']);
        DB::table('case_complainants')->insert(['blotter_case_id' => $id,
            'first_name' => 'Maria', 'last_name' => 'Reyes']);
        $this->getJson('/blotter?search=Pedro+Santos+Cruz')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/blotter?search=Maria+Cruz')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_reference_location_and_incident_filter_use_current_unarchived_records(): void
    {
        $id = $this->record(['location' => 'San Jose Main Street']);
        $this->record(['location' => 'San Jose Main Street', 'deleted_at' => now()]);
        $otherType = IncidentType::create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
        $this->record(['location' => 'San Jose Main Street', 'incident_type_id' => $otherType->id]);
        $this->getJson('/blotter?search=BSJ-TEST-1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/blotter?search=Main+Street&incident_type_id='.$this->incidentType->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.view_url', route('blotter.show', $id));
        $this->getJson('/blotter?search=not-found')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_dropdown_limits_initial_results_and_can_search_older_records(): void
    {
        for ($day = 1; $day <= 25; $day++) {
            $this->record(['reported_at' => sprintf('2026-09-%02d 12:00:00', $day)]);
        }
        $this->getJson('/blotter')->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.reference', 'BSJ-TEST-25');
        $this->getJson('/blotter?search=BSJ-TEST-1')->assertOk()
            ->assertJsonFragment(['reference' => 'BSJ-TEST-1']);
    }

    public function test_dropdown_respects_councilor_case_visibility(): void
    {
        $councilor = $this->user('councilor');
        $visible = $this->record();
        $hidden = $this->record();
        DB::table('case_assignments')->insert(['blotter_case_id' => $visible,
            'assigned_to' => $councilor->id, 'assigned_by' => $councilor->id, 'assigned_at' => now()]);
        $this->actingAs($councilor)->getJson('/blotter')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.view_url', route('blotter.show', $visible));
        $this->getJson('/blotter?search=BSJ-TEST-2')->assertOk()->assertJsonCount(0, 'data');
        $this->get('/blotter/'.$hidden)->assertForbidden();
    }

    public function test_dropdown_respects_lupon_case_visibility_and_requires_authentication(): void
    {
        $lupon = $this->user('lupon');
        $visible = $this->record();
        $this->record();
        DB::table('mediation_sessions')->insert(['blotter_case_id' => $visible,
            'hearing_number' => 1, 'scheduled_date' => '2026-09-02',
            'lupon_member_id' => $lupon->id, 'created_by' => $lupon->id]);
        $this->actingAs($lupon)->getJson('/blotter')->assertOk()->assertJsonCount(1, 'data');
        auth()->forgetGuards();
        $this->getJson('/blotter')->assertUnauthorized();
    }

    public function test_layered_search_retains_sitio_and_narrows_by_name_in_both_lists_and_suggestions(): void
    {
        $inside = $this->record();
        $outside = $this->record();
        $otherName = $this->record();
        foreach ([[$inside, 'Juan', 'Sitio 1'], [$outside, 'Juan', 'Sitio 2'], [$otherName, 'Pedro', 'Sitio 1']] as [$id, $name, $sitio]) {
            DB::table('case_complainants')->insert(['blotter_case_id' => $id,
                'first_name' => $name, 'last_name' => 'Cruz', 'sitio' => $sitio]);
        }
        foreach (['Sitio 1', 'Sitio Uno'] as $sitio) {
            $parameters = ['search_terms' => [$sitio], 'search' => 'Juan'];
            foreach (['/case-management', '/blotter'] as $path) {
                $this->get($path.'?'.http_build_query($parameters))->assertOk()
                    ->assertViewHas('cases', fn ($cases) => $cases->modelKeys() === [$inside])
                    ->assertViewHas('searchTerms', [$sitio, 'Juan'])
                    ->assertSee('name="search_terms[]"', false);
            }
            $this->getJson('/blotter?'.http_build_query($parameters))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.view_url', route('blotter.show', $inside));
        }
        $this->get('/case-management?search=Juan')->assertOk()->assertViewHas('cases', fn ($cases) => $cases->total() === 2);
        $this->get('/case-management')->assertOk()->assertViewHas('searchTerms', [])
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 3);
    }

    public function test_layered_search_keeps_visibility_incident_filters_and_pagination(): void
    {
        $councilor = $this->user('councilor');
        for ($i = 0; $i < 17; $i++) {
            $id = $this->record();
            DB::table('case_respondents')->insert(['blotter_case_id' => $id,
                'first_name' => 'Juan', 'last_name' => 'Cruz', 'sitio' => 'Sitio 1']);
            if ($i < 16) {
                DB::table('case_assignments')->insert(['blotter_case_id' => $id,
                    'assigned_to' => $councilor->id, 'assigned_by' => $councilor->id, 'assigned_at' => now()]);
            }
        }
        $parameters = ['search_terms' => ['Sitio 1'], 'search' => 'Juan',
            'incident_type_id' => $this->incidentType->id];
        $this->actingAs($councilor)->get('/case-management?'.http_build_query($parameters))->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->total() === 16)
            ->assertSee('search_terms', false);
        $parameters['page'] = 2;
        $this->get('/case-management?'.http_build_query($parameters))->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->count() === 1 && $cases->total() === 16);
        $parameters['incident_type_id'] = 99999;
        $this->getJson('/blotter?'.http_build_query($parameters))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_closed_outcomes_display_and_filter_without_changing_stored_stages(): void
    {
        $dismissed = $this->record(['status' => 'Dismissed', 'case_stage' => 'Closed',
            'record_status' => 'Closed', 'disposition' => 'Dismissed']);
        $referred = $this->record(['status' => 'Referred', 'case_stage' => 'Closed',
            'record_status' => 'Closed', 'disposition' => 'Referred']);
        $cfa = $this->record(['status' => 'Referred', 'case_stage' => 'Closed',
            'record_status' => 'Closed', 'disposition' => 'CFA Issued']);
        $legacy = $this->record(['status' => 'Dismissed', 'case_stage' => 'Closed', 'record_status' => 'Closed']);
        foreach (['Dismissed' => [$dismissed, $legacy], 'Referred' => [$referred], 'CFA Issued' => [$cfa]] as $label => $ids) {
            $this->get('/case-management?'.http_build_query(['stage' => $label, 'record_status' => 'Closed']))
                ->assertOk()->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->sort()->values()->all() === $ids);
        }
        foreach ([$dismissed => 'Dismissed', $referred => 'Referred', $cfa => 'CFA Issued', $legacy => 'Dismissed'] as $id => $label) {
            $case = BlotterCase::findOrFail($id);
            $this->assertSame($label, $case->workflowLabel());
            $this->assertSame(CaseStage::Closed, $case->case_stage);
        }
        $this->get('/blotter')->assertOk()->assertSee('CFA Issued')->assertSee('Dismissed');
    }

    public function test_invalid_search_parameters_are_rejected(): void
    {
        $this->getJson('/blotter?'.http_build_query(['search_terms' => 'invalid']))->assertUnprocessable();
        $this->getJson('/case-management?'.http_build_query(['search_terms' => [str_repeat('x', 256)]]))->assertUnprocessable();
    }

    private function user(string $slug): User
    {
        $role = Role::create(['name' => $slug, 'slug' => $slug]);

        return User::factory()->create(['username' => $slug.'-test', 'role_id' => $role->id, 'is_active' => true]);
    }

    private function record(array $values = []): int
    {
        return DB::table('blotter_cases')->insertGetId(array_merge([
            'reference_number' => 'BSJ-TEST-'.(++$this->sequence), 'incident_type_id' => $this->incidentType->id,
            'incident_date' => '2026-09-01', 'reported_at' => '2026-09-01 12:00:00',
            'location' => 'Test location', 'narrative' => 'Test fixture', 'status' => 'Pending',
            'case_stage' => 'New', 'record_status' => 'Open', 'created_at' => now(), 'updated_at' => now(),
        ], $values));
    }
}
