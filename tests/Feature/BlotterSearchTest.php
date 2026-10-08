<?php

namespace Tests\Feature;

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
