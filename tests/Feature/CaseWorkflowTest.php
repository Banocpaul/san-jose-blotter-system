<?php

namespace Tests\Feature;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\MediationSession;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemBackupService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaseWorkflowTest extends TestCase
{
    private array $users;

    private IncidentType $type;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 08:00:00', 'Asia/Manila'));
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => ! str_contains($path, 'update_case_resolution_workflow')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true])->assertSuccessful();
        foreach (['staff', 'secretary', 'councilor', 'barangay_captain', 'lupon'] as $slug) {
            $this->users[$slug] = $this->user($slug);
        }
        $this->type = IncidentType::create(['code' => 'TEST', 'name' => 'Test Incident', 'is_active' => true]);
    }

    public function test_staff_intake_secretary_assignment_and_councilor_handoff_do_not_return_404(): void
    {
        foreach (['Complainant', 'Respondent'] as $name) {
            $ids[] = DB::table('residents')->insertGetId(['resident_code' => $name,
                'first_name' => $name, 'last_name' => 'Person', 'is_active' => true]);
        }
        $this->actingAs($this->users['staff'])->post(route('blotter.store'), [
            'incident_type_id' => $this->type->id, 'incident_date' => '2026-10-08',
            'location' => 'Sitio Test', 'narrative' => 'Complaint details',
            'complainant_resident_id' => $ids[0], 'respondent_resident_id' => $ids[1],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $case = BlotterCase::firstOrFail();
        $reference = $case->reference_number;
        $this->assertStringStartsWith('BSJ-2026-', $reference);
        $this->assertState($case, CaseStage::New, RecordStatus::Open);
        $this->actingAs($this->users['secretary'])->post(route('cases.assess', $case))->assertSessionHasNoErrors();
        $this->assign($case);
        $this->actingAs($this->users['councilor'])->get(route('cases.show', $case))->assertOk()
            ->assertSee('Submit for Mediation Scheduling')->assertDontSee('Record Disposition and Close');
        $this->post(route('blotter.investigation-notes.store', $case), ['note' => 'Assessment completed'])
            ->assertSessionHasNoErrors();
        $response = $this->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Suitable for mediation']);
        $response->assertRedirect(route('cases.index'))->assertSessionHasNoErrors();
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertState($case, CaseStage::UnderAssessment, RecordStatus::Open);
        $this->assertDatabaseCount('mediation_sessions', 0);
        $this->assertNotNull($case->fresh()->mediation_requested_at);
        $this->assertNull($case->currentAssignment()->first());
        $this->assertSame($reference, $case->fresh()->reference_number);
    }

    public function test_mediation_scheduling_requires_date_time_officer_and_no_past_or_duplicate_hearings(): void
    {
        $case = $this->requestedCase();
        $this->actingAs($this->users['secretary'])->post(route('blotter.mediation.schedule', $case), [])
            ->assertSessionHasErrors(['scheduled_date', 'scheduled_time', 'venue', 'lupon_member_id']);
        $this->post(route('blotter.mediation.schedule', $case), $this->scheduleData(['scheduled_date' => '2026-10-09', 'scheduled_time' => '07:59']))
            ->assertSessionHasErrors('scheduled_time');
        $this->post(route('blotter.mediation.schedule', $case), $this->scheduleData(['lupon_member_id' => $this->users['staff']->id]))
            ->assertSessionHasErrors('lupon_member_id');
        $session = $this->schedule($case);
        $this->assertSame('2026-10-10', $session->scheduled_date->toDateString());
        $this->assertSame('10:00', substr($session->scheduled_time, 0, 5));
        $this->assertState($case, CaseStage::ForMediation, RecordStatus::Open);
        $this->post(route('blotter.mediation.schedule', $case), $this->scheduleData())->assertSessionHasErrors('mediation');
        $this->assertDatabaseCount('mediation_sessions', 1);
    }

    public function test_mediation_settlement_resolves_case_and_preserves_parties_history_and_analytics(): void
    {
        $case = $this->requestedCase();
        $this->addParties($case);
        $session = $this->schedule($case);
        $this->assertDatabaseCount('mediation_attendees', 2);
        $this->assertDatabaseCount('mediation_summons', 2);
        $this->travelTo(CarbonImmutable::parse('2026-10-10 11:00', 'Asia/Manila'));
        $this->actingAs($this->users['lupon'])->post(route('mediation.outcome.store', $session), ['outcome' => 'Settled'])
            ->assertSessionHasErrors('agreement_details');
        $this->post(route('mediation.outcome.store', $session), ['outcome' => 'Settled', 'agreement_details' => 'Written agreement'])
            ->assertSessionHasNoErrors();
        $this->assertState($case, CaseStage::SettledResolved, RecordStatus::Resolved);
        $this->assertSame('Mediation Settlement', $case->fresh()->disposition);
        $resolution = $case->caseResolution()->firstOrFail();
        $this->actingAs($this->users['secretary'])->patch(route('settlements.complete', $resolution))->assertSessionHasNoErrors();
        $this->assertSame('Completed', $resolution->fresh()->status);
        $this->assertDatabaseCount('case_complainants', 1);
        $this->assertDatabaseCount('case_respondents', 1);
        $this->assertDatabaseCount('mediation_outcomes', 1);
        $this->get('/analytics?tab=performance')->assertOk()->assertViewHas('resolvedCases', 1);
        $this->get(route('blotter.show', $case))->assertOk()->assertSee('Mediation Settlement')->assertDontSee('Record Disposition and Close');
    }

    public function test_pangkat_failure_stays_open_until_documented_final_disposition(): void
    {
        [$case, $session] = $this->pangkatCase();
        $this->outcome($session, 'No Agreement');
        $this->assertState($case, CaseStage::ForFurtherActionCfa, RecordStatus::Open);
        $this->assertNull($case->fresh()->closed_at);
        $this->actingAs($this->users['secretary'])->get(route('cases.show', $case))->assertOk()
            ->assertSee('Further Action / CFA and Final Disposition');
        $data = ['disposition' => 'CFA Issued', 'disposition_reason' => 'Conciliation failed'];
        $this->post(route('cases.dispose', $case), $data)->assertSessionHasErrors('further_action_documentation');
        $this->post(route('cases.dispose', $case), $data + ['further_action_documentation' => 'CFA-2026-001 prepared and recorded'])
            ->assertSessionHasNoErrors();
        $this->assertState($case, CaseStage::Closed, RecordStatus::Closed);
        $this->assertSame('CFA Issued', $case->fresh()->disposition);
        $this->assertNotNull($case->fresh()->closed_at);
        $this->post(route('cases.dispose', $case), $data)->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('mediation_sessions', 2);
    }

    public function test_pangkat_settlement_remains_distinct_when_settlement_record_is_completed(): void
    {
        [$case, $session] = $this->pangkatCase();
        $this->outcome($session, 'Settled');
        $this->assertState($case, CaseStage::SettledResolved, RecordStatus::Resolved);
        $resolution = $case->caseResolution()->firstOrFail();
        $this->assertSame('Pangkat Settlement', $resolution->resolution_type);
        $this->actingAs($this->users['secretary'])->patch(route('settlements.complete', $resolution))->assertSessionHasNoErrors();
        $this->assertSame('Pangkat Settlement', $resolution->fresh()->resolution_type);
        $this->assertSame('Pangkat Settlement', $case->fresh()->disposition);
    }

    public function test_assessment_referral_and_dismissal_require_reasons_and_close_without_deleting_records(): void
    {
        foreach (['Referred', 'Dismissed'] as $disposition) {
            $case = $this->case(['case_stage' => CaseStage::UnderAssessment, 'status' => CaseStatus::UnderInvestigation]);
            $this->assign($case);
            $this->actingAs($this->users['secretary'])->post(route('cases.dispose', $case), ['disposition' => $disposition])
                ->assertSessionHasErrors('disposition_reason');
            if ($disposition === 'Referred') {
                $this->post(route('cases.dispose', $case), ['disposition' => $disposition, 'disposition_reason' => 'Outside process'])
                    ->assertSessionHasErrors('referral_agency');
            }
            $this->post(route('cases.dispose', $case), ['disposition' => $disposition,
                'disposition_reason' => 'Recorded assessment decision', 'referral_agency' => 'Appropriate agency'])
                ->assertSessionHasNoErrors();
            $this->assertState($case, CaseStage::Closed, RecordStatus::Closed);
            $this->assertSame($disposition, $case->fresh()->disposition);
            $this->assertNull($case->fresh()->deleted_at);
            $this->assertNull($case->currentAssignment()->first());
            $this->get(route('cases.show', $case))->assertOk()->assertSee('Recorded assessment decision');
        }
    }

    public function test_roles_and_assignment_scopes_are_enforced_on_workflow_actions(): void
    {
        $case = $this->requestedCase();
        foreach (['staff', 'councilor', 'lupon'] as $role) {
            $this->actingAs($this->users[$role])->post(route('cases.assess', $case))->assertForbidden();
            $this->post(route('cases.dispose', $case), ['disposition' => 'Dismissed', 'disposition_reason' => 'Attempt'])
                ->assertForbidden();
            $this->post(route('cases.pangkat', $case), ['pangkat_member_ids' => [1, 2, 3]])->assertForbidden();
            $this->post(route('blotter.mediation.schedule', $case), $this->scheduleData())->assertForbidden();
        }
        $unassigned = $this->case(['case_stage' => CaseStage::UnderAssessment]);
        $this->actingAs($this->user('councilor'))->post(route('blotter.mediation.refer', $unassigned), ['mediation_request_reason' => 'Attempt'])
            ->assertForbidden();
        $session = $this->schedule($case);
        $this->actingAs($this->user('lupon'))->post(route('mediation.outcome.store', $session), ['outcome' => 'Settled', 'agreement_details' => 'Attempt'])
            ->assertForbidden();
        $inactive = $this->user('secretary');
        $inactive->update(['is_active' => false]);
        $this->actingAs($inactive)->post(route('cases.dispose', $case), [])->assertRedirect(route('login'));
    }

    public function test_general_case_edit_cannot_change_or_reset_workflow(): void
    {
        $case = $this->requestedCase();
        $session = $this->schedule($case);
        $this->outcome($session, 'No Agreement');
        $data = ['incident_type_id' => $this->type->id, 'incident_date' => '2026-10-08', 'location' => 'Edited', 'narrative' => 'Edited details'];
        foreach (['staff', 'secretary', 'barangay_captain'] as $role) {
            $this->actingAs($this->users[$role])->put(route('cases.update', $case), $data + ['status' => 'Settled'])->assertSessionHasErrors('status');
            $this->put(route('cases.update', $case), $data)->assertSessionHasNoErrors();
            $this->assertState($case, CaseStage::ForMediation, RecordStatus::Open);
        }
        $this->assertDatabaseCount('mediation_outcomes', 1);
        $this->assertNull($case->fresh()->closed_at);
    }

    public function test_invalid_sequence_and_duplicate_actions_cannot_skip_workflow_steps(): void
    {
        $case = $this->case();
        $this->actingAs($this->users['secretary'])->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Attempt'])
            ->assertSessionHasErrors('workflow');
        $this->post(route('blotter.mediation.schedule', $case), $this->scheduleData())->assertSessionHasErrors('workflow');
        $this->post(route('cases.dispose', $case), ['disposition' => 'Dismissed', 'disposition_reason' => 'Attempt'])
            ->assertSessionHasErrors('workflow');
        $this->assign($case);
        $this->actingAs($this->users['councilor'])->post(route('blotter.mediation.refer', $case), [])->assertSessionHasErrors('mediation_request_reason');
        $this->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Suitable'])->assertSessionHasNoErrors();
        $this->assertNull($case->currentAssignment()->first());
        $this->actingAs($this->users['secretary'])->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Repeat'])->assertSessionHasErrors('mediation');
        $this->post(route('blotter.assign', $case), ['assigned_to' => $this->users['councilor']->id])->assertSessionHasErrors('assignment');
        $session = $this->schedule($case);
        $this->actingAs($this->users['lupon'])->post(route('mediation.outcome.store', $session), ['outcome' => 'Settled', 'agreement_details' => 'Attempt early'])
            ->assertSessionHasErrors('outcome');
        $this->outcome($session, 'Settled');
        $this->post(route('mediation.outcome.store', $session), ['outcome' => 'No Agreement', 'remarks' => 'Repeat'])->assertSessionHasErrors('outcome');
        $this->assertDatabaseCount('mediation_outcomes', 1);
    }

    public function test_backup_accepts_pre_workflow_schema_and_retains_current_workflow_fields(): void
    {
        $case = $this->requestedCase();
        $service = app(SystemBackupService::class);
        $document = $service->decodePayload($service->createPayload());
        $service->decodePayload(Crypt::encryptString(base64_encode(gzencode(json_encode($document)))));
        $this->assertSame('Suitable for mediation', $document['tables']['blotter_cases'][0]['mediation_request_reason']);
        $columns = ['mediation_requested_at', 'mediation_request_reason', 'pangkat_members', 'pangkat_constituted_at',
            'disposition', 'disposition_reason', 'referral_agency', 'further_action_documentation', 'disposed_by', 'disposed_at'];
        $document['schema']['blotter_cases'] = array_values(array_diff($document['schema']['blotter_cases'], $columns));
        foreach ($document['tables']['blotter_cases'] as &$row) {
            foreach ($columns as $column) {
                unset($row[$column]);
            }
        }
        unset($row);
        $service->decodePayload(Crypt::encryptString(base64_encode(gzencode(json_encode($document)))));
        $this->assertNotNull($case->fresh()->mediation_requested_at);
        $reference = $case->reference_number;
        $service->restore($document);
        $this->assertSame($reference, $case->fresh()->reference_number);
        $this->assertSame('Historical narrative', $case->fresh()->narrative);
        $this->assertNull($case->fresh()->mediation_requested_at);
    }

    public function test_rescheduled_hearing_requires_an_explicit_new_schedule_without_duplicate_outcomes(): void
    {
        $case = $this->requestedCase();
        $session = $this->schedule($case);
        $this->actingAs($this->users['lupon'])->post(route('mediation.outcome.store', $session), ['outcome' => 'Rescheduled', 'remarks' => 'Parties requested a new date'])
            ->assertSessionHasNoErrors();
        $this->assertState($case, CaseStage::ForMediation, RecordStatus::Open);
        $this->assertSame('Rescheduled', $session->fresh()->status);
        $next = $this->schedule($case, ['scheduled_date' => '2026-10-12']);
        $this->assertSame(2, $next->hearing_number);
        $this->assertSame('2026-10-12', $next->scheduled_date->toDateString());
        $this->assertDatabaseCount('mediation_outcomes', 1);
    }

    public function test_notices_attendance_and_outcomes_obey_assigned_hearing_permissions_and_terminal_locks(): void
    {
        $case = $this->requestedCase();
        $this->addParties($case);
        $session = $this->schedule($case);
        $summons = $session->summons()->firstOrFail();
        $attendee = $session->attendees()->firstOrFail();
        foreach ([$this->users['staff'], $this->users['councilor'], $this->user('lupon')] as $user) {
            $this->actingAs($user)->patch(route('mediation.summons.update', $summons), ['status' => 'Prepared'])->assertForbidden();
            $this->patch(route('mediation.attendance.update', $attendee), ['is_present' => true])->assertForbidden();
        }
        $this->actingAs($this->users['lupon'])->patch(route('mediation.summons.update', $summons), ['status' => 'Received'])->assertSessionHasNoErrors();
        $this->assertNotNull($summons->fresh()->received_at);
        $this->patch(route('mediation.attendance.update', $attendee), ['is_present' => true])->assertSessionHasNoErrors();
        $this->assertTrue($attendee->fresh()->is_present);
        $this->outcome($session, 'Settled');
        $this->patch(route('mediation.summons.update', $summons), ['status' => 'Failed Delivery'])->assertSessionHasErrors('summons');
        $this->patch(route('mediation.attendance.update', $attendee), ['is_present' => false])->assertSessionHasErrors('attendance');
        $this->assertTrue($attendee->fresh()->is_present);
    }

    public function test_workflow_forms_match_the_authenticated_roles(): void
    {
        $case = $this->requestedCase();
        $this->actingAs($this->users['staff'])->get(route('cases.show', $case))->assertOk()
            ->assertDontSee('name="scheduled_date"', false)->assertDontSee('name="disposition"', false)->assertDontSee('name="assigned_to"', false);
        $this->actingAs($this->users['secretary'])->get(route('cases.show', $case))->assertOk()->assertSee('name="scheduled_date"', false);
        $session = $this->schedule($case);
        $this->actingAs($this->users['lupon'])->get(route('cases.show', $case))->assertOk()
            ->assertSee('name="outcome"', false)->assertDontSee('name="scheduled_date"', false)->assertDontSee('name="disposition"', false);
        $this->get(route('blotter.show', $case))->assertOk()->assertDontSee('name="outcome"', false);
        $this->actingAs($this->user('lupon'))->get(route('cases.show', $case))->assertForbidden();
    }

    public function test_captain_can_preside_over_mediation_and_separate_documentation_does_not_close_a_case(): void
    {
        $case = $this->requestedCase();
        $session = $this->schedule($case, ['lupon_member_id' => $this->users['barangay_captain']->id]);
        $this->travelTo(CarbonImmutable::parse('2026-10-10 11:00', 'Asia/Manila'));
        $this->actingAs($this->users['barangay_captain'])->post(route('mediation.outcome.store', $session), ['outcome' => 'Settled', 'agreement_details' => 'Agreement recorded'])
            ->assertSessionHasNoErrors();
        $this->assertState($case, CaseStage::SettledResolved, RecordStatus::Resolved);
        $this->travelTo(CarbonImmutable::parse('2026-10-09 08:00', 'Asia/Manila'));
        [$cfaCase, $pangkat] = $this->pangkatCase();
        $this->outcome($pangkat, 'No Agreement');
        $this->actingAs($this->users['secretary'])->post(route('cases.documentation', $cfaCase), ['further_action_documentation' => 'Prepared CFA-001'])
            ->assertSessionHasNoErrors();
        $this->assertState($cfaCase, CaseStage::ForFurtherActionCfa, RecordStatus::Open);
        $this->post(route('cases.dispose', $cfaCase), ['disposition' => 'CFA Issued', 'disposition_reason' => 'Failed conciliation'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Prepared CFA-001', $cfaCase->fresh()->further_action_documentation);
    }

    public function test_legacy_pangkat_records_can_continue_without_rewriting_their_history(): void
    {
        $case = $this->case(['case_stage' => CaseStage::ForPangkatConciliation, 'status' => CaseStatus::ForMediation]);
        $members = [$this->users['lupon']->id, $this->user('lupon')->id, $this->user('lupon')->id];
        $this->actingAs($this->users['secretary'])->post(route('cases.pangkat', $case), ['pangkat_member_ids' => $members])->assertSessionHasNoErrors();
        $session = $this->schedule($case);
        $this->assertSame('Pangkat Conciliation', $session->proceeding_type);
        $this->assertSame('Historical narrative', $case->fresh()->narrative);
    }

    public function test_legacy_premature_cfa_closure_can_be_explicitly_resumed_but_final_dispositions_cannot(): void
    {
        [$case, $session] = $this->pangkatCase();
        $this->outcome($session, 'No Agreement');
        $case->update(['status' => CaseStatus::Referred, 'record_status' => RecordStatus::Closed, 'closed_at' => now()]);
        $this->actingAs($this->users['secretary'])->post(route('cases.resume-further-action', $case), [])->assertSessionHasErrors('resume_reason');
        $this->post(route('cases.resume-further-action', $case), ['resume_reason' => 'Complete missing final-disposition workflow'])->assertSessionHasNoErrors();
        $this->assertState($case, CaseStage::ForFurtherActionCfa, RecordStatus::Open);
        $this->assertDatabaseCount('mediation_outcomes', 2);
        $this->post(route('cases.dispose', $case), ['disposition' => 'CFA Issued', 'disposition_reason' => 'Failed conciliation', 'further_action_documentation' => 'CFA-001'])
            ->assertSessionHasNoErrors();
        $this->post(route('cases.resume-further-action', $case), ['resume_reason' => 'Attempt'])->assertSessionHasErrors('workflow');
        $this->assertState($case, CaseStage::Closed, RecordStatus::Closed);
    }

    public function test_workflow_migration_adds_fields_without_changing_existing_case_or_party_records(): void
    {
        $migration = require database_path('migrations/2026_10_09_042441_add_official_workflow_fields_to_blotter_cases.php');
        $migration->down();
        $id = DB::table('blotter_cases')->insertGetId(['reference_number' => 'BSJ-LEGACY-001', 'incident_type_id' => $this->type->id,
            'incident_date' => '2026-01-01', 'narrative' => 'Unchanged legacy narrative', 'location' => 'Legacy location',
            'status' => 'For Mediation', 'case_stage' => 'For Pangkat/Conciliation', 'record_status' => 'Open',
            'reported_at' => '2026-01-01 12:00:00', 'closed_at' => null]);
        DB::table('case_complainants')->insert(['blotter_case_id' => $id, 'first_name' => 'Legacy', 'last_name' => 'Person']);
        $before = (array) DB::table('blotter_cases')->find($id);
        $migration->up();
        $after = (array) DB::table('blotter_cases')->find($id);
        $this->assertSame($before, array_intersect_key($after, $before));
        $this->assertNull($after['disposition']);
        $this->assertDatabaseHas('case_complainants', ['blotter_case_id' => $id, 'first_name' => 'Legacy']);
    }

    public function test_pangkat_can_record_named_members_without_creating_accounts_or_expanding_permissions(): void
    {
        $case = $this->requestedCase();
        $mediation = $this->schedule($case);
        $this->outcome($mediation, 'No Agreement');
        $this->actingAs($this->users['secretary'])->post(route('cases.pangkat', $case), [
            'pangkat_presiding_member_id' => $this->users['lupon']->id,
            'pangkat_member_names' => ['First Committee Member', 'Second Committee Member'],
        ])->assertSessionHasNoErrors();
        $this->assertCount(3, $case->fresh()->pangkat_members);
        $this->assertDatabaseCount('users', 5);
        $session = $this->schedule($case, ['scheduled_date' => '2026-10-11']);
        $this->assertSame('Pangkat Conciliation', $session->proceeding_type);
        $this->actingAs($this->users['staff'])->post(route('mediation.outcome.store', $session), ['outcome' => 'Settled'])->assertForbidden();
    }

    private function pangkatCase(): array
    {
        $case = $this->requestedCase();
        $mediation = $this->schedule($case);
        $this->outcome($mediation, 'No Agreement');
        $this->assertState($case, CaseStage::ForMediation, RecordStatus::Open);
        $this->actingAs($this->users['secretary'])->post(route('blotter.mediation.schedule', $case), $this->scheduleData(['scheduled_date' => '2026-10-11']))
            ->assertSessionHasErrors('pangkat');
        $members = [$this->users['lupon'], $this->user('lupon'), $this->user('lupon')];
        $ids = array_map(fn ($member) => $member->id, $members);
        $this->post(route('cases.pangkat', $case), ['pangkat_member_ids' => [$ids[0], $ids[0], $ids[1]]])
            ->assertSessionHasErrors('pangkat_member_ids.0');
        $this->post(route('cases.pangkat', $case), ['pangkat_member_ids' => $ids])->assertSessionHasNoErrors();
        $this->assertCount(3, $case->fresh()->pangkat_members);
        $this->post(route('blotter.mediation.schedule', $case), $this->scheduleData(['scheduled_date' => '2026-10-11', 'lupon_member_id' => $this->user('lupon')->id]))
            ->assertSessionHasErrors('lupon_member_id');
        $session = $this->schedule($case, ['scheduled_date' => '2026-10-11']);
        $this->assertSame('Pangkat Conciliation', $session->proceeding_type);
        $this->assertState($case, CaseStage::ForPangkatConciliation, RecordStatus::Open);
        $this->travelTo(CarbonImmutable::parse('2026-10-11 11:00', 'Asia/Manila'));

        return [$case, $session];
    }

    private function requestedCase(): BlotterCase
    {
        $case = $this->case();
        $this->assign($case);
        $this->actingAs($this->users['councilor'])->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Suitable for mediation'])
            ->assertSessionHasNoErrors();

        return $case->fresh();
    }

    private function assign(BlotterCase $case): void
    {
        $this->actingAs($this->users['secretary'])->post(route('blotter.assign', $case), ['assigned_to' => $this->users['councilor']->id])
            ->assertSessionHasNoErrors();
    }

    private function scheduleData(array $overrides = []): array
    {
        return array_merge(['scheduled_date' => '2026-10-10', 'scheduled_time' => '10:00', 'venue' => 'Barangay Hall',
            'lupon_member_id' => $this->users['lupon']->id], $overrides);
    }

    private function schedule(BlotterCase $case, array $overrides = []): MediationSession
    {
        $this->actingAs($this->users['secretary'])->post(route('blotter.mediation.schedule', $case), $this->scheduleData($overrides))
            ->assertSessionHasNoErrors();

        return $case->mediationSessions()->latest('hearing_number')->firstOrFail();
    }

    private function outcome(MediationSession $session, string $outcome): void
    {
        $this->travelTo(CarbonImmutable::parse($session->scheduled_date->toDateString().' 11:00', 'Asia/Manila'));
        $this->actingAs($this->users['lupon'])->post(route('mediation.outcome.store', $session), ['outcome' => $outcome,
            'remarks' => 'Proceeding recorded', 'agreement_details' => $outcome === 'Settled' ? 'Written terms agreed' : null])
            ->assertSessionHasNoErrors();
    }

    private function user(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug]);

        return User::factory()->create(['username' => $slug.'-'.(++$this->sequence), 'role_id' => $role->id, 'is_active' => true]);
    }

    private function case(array $overrides = []): BlotterCase
    {
        return BlotterCase::create(array_merge(['incident_type_id' => $this->type->id, 'incident_date' => '2026-10-08',
            'location' => 'Test location', 'narrative' => 'Historical narrative', 'created_by' => $this->users['staff']->id], $overrides));
    }

    private function addParties(BlotterCase $case): void
    {
        $case->complainants()->create(['first_name' => 'First', 'last_name' => 'Complainant']);
        $case->respondents()->create(['first_name' => 'First', 'last_name' => 'Respondent']);
    }

    private function assertState(BlotterCase $case, CaseStage $stage, RecordStatus $status): void
    {
        $this->assertSame($stage, $case->fresh()->case_stage);
        $this->assertSame($status, $case->fresh()->record_status);
    }
}
