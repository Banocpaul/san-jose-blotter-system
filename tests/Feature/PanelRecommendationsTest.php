<?php

namespace Tests\Feature;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Jobs\SendCasePartyNotification;
use App\Mail\CasePartyUpdate;
use App\Models\BlotterCase;
use App\Models\CaseNotificationContact;
use App\Models\CaseNotificationDelivery;
use App\Models\IncidentType;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseJurisdictionService;
use App\Services\CaseNotificationService;
use App\Services\SystemBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PanelRecommendationsTest extends TestCase
{
    private array $users = [];

    private IncidentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setTimezone('Asia/Manila')->setDate(2026, 10, 17)->setTime(8, 0));
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => ! str_contains($path, 'update_case_resolution_workflow')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true])->assertSuccessful();
        foreach (['barangay_captain', 'secretary', 'staff', 'councilor', 'lupon'] as $slug) {
            $role = Role::create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
            $this->users[$slug] = User::factory()->create(['username' => $slug, 'role_id' => $role->id,
                'password' => 'Test-password-123', 'is_active' => true]);
        }
        $this->type = IncidentType::create(['code' => 'TEST', 'name' => 'Test category', 'is_active' => true]);
        $this->actingAs($this->users['secretary']);
    }


    public function test_resend_api_submits_real_transport_request_without_smtp(): void
    {
        Queue::fake();
        Http::fake(['api.resend.com/emails' => Http::response(['id' => 'email_test_123'], 200)]);
        config()->set('case_notifications.enabled', true);
        config()->set('case_notifications.mailer', 'resend');
        config()->set('services.resend.key', 'test-only-resend-key');
        config()->set('mail.from.address', 'notice@example.test');
        config()->set('mail.from.name', 'San Jose Blotter System');

        $case = $this->case();
        $this->contact($case);
        app(CaseNotificationService::class)->caseUpdated($case);
        $delivery = CaseNotificationDelivery::firstOrFail();

        $this->assertSame('queued', $delivery->status);
        (new SendCasePartyNotification($delivery->id))->handle(app(CaseNotificationService::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.resend.com/emails'
            && $request->hasHeader('Authorization', 'Bearer test-only-resend-key')
            && $request['to'] === ['party@example.test']
            && $request['subject'] === 'Barangay San Jose: record update');
        $this->assertSame('submitted', $delivery->fresh()->status);
    }

    public function test_resend_requires_an_api_key(): void
    {
        config()->set('case_notifications.enabled', true);
        config()->set('case_notifications.mailer', 'resend');
        config()->set('services.resend.key', null);
        $this->assertFalse(app(CaseNotificationService::class)->isConfigured());
    }

    public function test_deactivated_accounts_and_roles_cannot_reuse_private_routes(): void
    {
        $this->get(route('roles.responsibilities'))->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->users['secretary']->update(['is_active' => false]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->users['staff']->role->update(['is_active' => false]);
        $this->actingAs($this->users['staff'])->get(route('roles.responsibilities'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sign_in_is_throttled_after_repeated_failed_passwords(): void
    {
        auth()->logout();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.attempt'), ['username' => 'secretary', 'password' => 'incorrect'])
                ->assertSessionHasErrors('username');
        }
        $this->post(route('login.attempt'), ['username' => 'secretary', 'password' => 'Test-password-123'])
            ->assertSessionHasErrors(['username' => 'Too many sign-in attempts. Please try again in a minute.']);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login.attempt'), ['username' => 'secretary', 'password' => 'Test-password-123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->users['secretary']);
    }

    public function test_jurisdiction_review_requires_an_official_and_documented_findings(): void
    {
        $case = $this->case();
        foreach (['staff', 'councilor', 'lupon'] as $role) {
            $this->actingAs($this->users[$role])->put(route('cases.jurisdiction', $case), $this->reviewData())->assertForbidden();
        }
        $this->actingAs($this->users['secretary'])->put(route('cases.jurisdiction', $case), [])
            ->assertSessionHasErrors(['decision', 'authority_basis', 'venue_basis', 'exclusions_reviewed']);
        $this->put(route('cases.jurisdiction', $case), $this->reviewData(['decision' => 'for_referral']))
            ->assertSessionHasErrors('referral_agency');
        $this->approve($case);
        $this->assertDatabaseHas('case_jurisdiction_assessments', ['blotter_case_id' => $case->id,
            'decision' => 'within_authority', 'reviewed_by' => $this->users['secretary']->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'jurisdiction_reviewed', 'auditable_id' => $case->id]);
    }

    public function test_pending_referral_and_stale_reviews_cannot_enter_mediation(): void
    {
        $case = $this->case();
        $case->assignments()->create(['assigned_to' => $this->users['councilor']->id,
            'assigned_by' => $this->users['secretary']->id, 'assigned_at' => now()]);
        $this->actingAs($this->users['councilor'])->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Assessment complete'])
            ->assertSessionHasErrors('jurisdiction');
        foreach (['for_referral', 'legal_review'] as $decision) {
            $this->actingAs($this->users['secretary'])->put(route('cases.jurisdiction', $case),
                $this->reviewData(['decision' => $decision, 'referral_agency' => 'City legal office']))->assertSessionHasNoErrors();
            $this->actingAs($this->users['councilor'])->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Assessment complete'])
                ->assertSessionHasErrors('jurisdiction');
        }
        $this->approve($case);
        $case->complainants()->first()->update(['address' => 'New facts require a venue review']);
        $this->assertFalse(app(CaseJurisdictionService::class)->isApproved($case->fresh()));
        $this->actingAs($this->users['councilor'])->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Assessment complete'])
            ->assertSessionHasErrors('jurisdiction');
        $this->approve($case);
        $this->actingAs($this->users['councilor'])->post(route('blotter.mediation.refer', $case), ['mediation_request_reason' => 'Assessment complete'])
            ->assertSessionHasNoErrors();
        $case->fresh()->update(['narrative' => 'Changed incident facts']);
        $this->actingAs($this->users['secretary'])->post(route('blotter.mediation.schedule', $case), $this->hearingData())
            ->assertSessionHasErrors('jurisdiction');
        $this->assertDatabaseCount('mediation_sessions', 0);
    }

    public function test_notification_contacts_require_verification_agreement_and_case_membership(): void
    {
        $case = $this->case();
        $other = $this->case();
        $data = $this->contactData($case);
        $this->post(route('cases.notifications.store', $case), ['party' => $data['party'], 'email' => $data['email']])
            ->assertSessionHasErrors(['contact_verified', 'notification_consent']);
        $this->post(route('cases.notifications.store', $case), $this->contactData($other))->assertNotFound();
        $this->actingAs($this->users['staff'])->post(route('cases.notifications.store', $case), $data)->assertForbidden();
        $this->actingAs($this->users['secretary'])->post(route('cases.notifications.store', $case), $data)
            ->assertSessionHasNoErrors();
        $contact = CaseNotificationContact::firstOrFail();
        $raw = DB::table('case_notification_contacts')->value('email');
        $this->assertStringNotContainsString($data['email'], $raw);
        $this->assertSame($data['email'], $contact->email);
        $this->get(route('cases.notifications.index', $case))->assertOk()->assertSee('p***@example.test')
            ->assertDontSee($data['email']);
        $this->patch(route('cases.notifications.disable', [$other, $contact]))->assertNotFound();
    }

    public function test_hearing_event_queues_a_private_party_notice_and_records_submission(): void
    {
        Queue::fake();
        Mail::fake();
        $this->enableMail();
        $case = $this->case();
        $contact = $this->contact($case);
        $this->approve($case);
        $case->update(['mediation_requested_at' => now()]);
        $this->post(route('blotter.mediation.schedule', $case), $this->hearingData())->assertSessionHasNoErrors();
        Queue::assertPushed(SendCasePartyNotification::class, 1);
        $delivery = CaseNotificationDelivery::firstOrFail();
        $this->assertSame('queued', $delivery->status);
        $this->assertStringContainsString('October 18, 2026 at 10:00', $delivery->message);
        $this->assertStringNotContainsString('Private incident narrative', $delivery->message);
        $this->assertStringNotContainsString('Respondent Secret', $delivery->message);
        $this->assertStringNotContainsString($case->reference_number, DB::table('case_notification_deliveries')->value('message'));
        $job = new SendCasePartyNotification($delivery->id);
        $job->handle(app(CaseNotificationService::class));
        $job->handle(app(CaseNotificationService::class));
        Mail::assertSent(CasePartyUpdate::class, fn ($mail) => $mail->hasTo($contact->email));
        Mail::assertSentCount(1);
        $this->assertSame('submitted', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->submitted_at);
    }

    public function test_disabled_and_replaced_contacts_never_receive_stale_jobs(): void
    {
        Queue::fake();
        Mail::fake();
        $this->enableMail();
        $case = $this->case();
        $contact = $this->contact($case);
        app(CaseNotificationService::class)->caseUpdated($case);
        $old = CaseNotificationDelivery::firstOrFail();
        $this->post(route('cases.notifications.store', $case), $this->contactData($case, 'replacement@example.test'))
            ->assertSessionHasNoErrors();
        (new SendCasePartyNotification($old->id))->handle(app(CaseNotificationService::class));
        $this->assertSame('canceled', $old->fresh()->status);
        $this->assertSame('party@example.test', $old->fresh()->recipient_email);
        $this->assertStringNotContainsString('party@example.test', DB::table('case_notification_deliveries')->where('id', $old->id)->value('recipient_email'));
        app(CaseNotificationService::class)->caseUpdated($case);
        $pending = CaseNotificationDelivery::where('status', 'queued')->firstOrFail();
        $this->patch(route('cases.notifications.disable', [$case, $contact]))->assertSessionHasNoErrors();
        (new SendCasePartyNotification($pending->id))->handle(app(CaseNotificationService::class));
        Mail::assertNothingSent();
        $this->assertSame('canceled', $pending->fresh()->status);
    }

    public function test_unconfigured_or_logging_mailers_do_not_claim_a_notification_was_sent(): void
    {
        Queue::fake();
        Mail::fake();
        $case = $this->case();
        $this->contact($case);
        config(['case_notifications.enabled' => true, 'case_notifications.mailer' => 'log']);
        app(CaseNotificationService::class)->caseUpdated($case);
        $delivery = CaseNotificationDelivery::firstOrFail();
        $this->assertSame('not_configured', $delivery->status);
        Queue::assertNothingPushed();
        $this->post(route('cases.notifications.retry', [$case, $delivery]))->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_rolled_back_workflows_do_not_create_notices_or_queue_jobs(): void
    {
        Mail::fake();
        $this->enableMail();
        config(['queue.default' => 'database']);
        $case = $this->case();
        $this->contact($case);
        try {
            DB::transaction(function () use ($case): void {
                $case->update(['case_stage' => CaseStage::Closed, 'record_status' => RecordStatus::Closed]);
                throw new RuntimeException('Rollback this transaction');
            });
        } catch (RuntimeException) {
        }
        $this->assertDatabaseCount('case_notification_deliveries', 0);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingSent();
    }

    public function test_mail_failure_is_retriable_without_exposing_provider_details(): void
    {
        Queue::fake();
        $this->enableMail();
        $case = $this->case();
        $this->contact($case);
        app(CaseNotificationService::class)->caseUpdated($case);
        $delivery = CaseNotificationDelivery::firstOrFail();
        Mail::shouldReceive('mailer')->once()->andReturnSelf();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Private provider and recipient details'));
        $job = new SendCasePartyNotification($delivery->id);
        try {
            $job->handle(app(CaseNotificationService::class));
            $this->fail('A failed transport must request a retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Case notification could not be submitted to the mail service.', $exception->getMessage());
        }
        $this->assertSame('retrying', $delivery->fresh()->status);
        $job->failed(null);
        $this->assertSame('failed', $delivery->fresh()->status);
        $other = $this->case();
        $this->post(route('cases.notifications.retry', [$other, $delivery]))->assertNotFound();
        $this->post(route('cases.notifications.retry', [$case, $delivery]))->assertSessionHasNoErrors();
        $this->assertSame('queued', $delivery->fresh()->status);
    }

    public function test_backups_preserve_reviews_and_notification_history_and_accept_older_files(): void
    {
        Queue::fake();
        $case = $this->case();
        $this->contact($case);
        $this->approve($case);
        app(CaseNotificationService::class)->caseUpdated($case);
        $service = app(SystemBackupService::class);
        $payload = $service->createPayload();
        $this->assertStringNotContainsString('party@example.test', $payload);
        $document = $service->decodePayload($payload);
        $service->restore($document);
        $this->assertDatabaseCount('case_jurisdiction_assessments', 1);
        $this->assertDatabaseCount('case_notification_contacts', 1);
        $this->assertDatabaseCount('case_notification_deliveries', 1);
        foreach (['case_jurisdiction_assessments', 'case_notification_contacts', 'case_notification_deliveries'] as $table) {
            unset($document['tables'][$table], $document['schema'][$table], $document['counts'][$table]);
        }
        $service->restore($document);
        $this->assertDatabaseCount('blotter_cases', 1);
        $this->assertDatabaseCount('case_notification_contacts', 0);
        $this->assertDatabaseCount('case_notification_deliveries', 0);
        $this->assertFalse(app(CaseJurisdictionService::class)->isApproved($case->fresh()));
    }

    private function case(): BlotterCase
    {
        $case = BlotterCase::create(['incident_type_id' => $this->type->id, 'incident_date' => '2026-10-16',
            'location' => 'Test venue', 'narrative' => 'Private incident narrative', 'case_stage' => CaseStage::UnderAssessment,
            'created_by' => $this->users['staff']->id]);
        $case->complainants()->create(['first_name' => 'Complainant', 'last_name' => 'Private', 'address' => 'Test address']);
        $case->respondents()->create(['first_name' => 'Respondent', 'last_name' => 'Secret', 'address' => 'Test address']);

        return $case;
    }

    private function reviewData(array $overrides = []): array
    {
        return array_merge(['decision' => 'within_authority', 'authority_basis' => 'Official test assessment of legal coverage.',
            'venue_basis' => 'Official test review of actual residence and proper venue.', 'exclusions_reviewed' => '1'], $overrides);
    }

    private function approve(BlotterCase $case): void
    {
        $this->actingAs($this->users['secretary'])->put(route('cases.jurisdiction', $case), $this->reviewData())
            ->assertSessionHasNoErrors();
    }

    private function hearingData(): array
    {
        return ['scheduled_date' => '2026-10-18', 'scheduled_time' => '10:00', 'venue' => 'Barangay Hall',
            'lupon_member_id' => $this->users['lupon']->id];
    }

    private function contactData(BlotterCase $case, string $email = 'party@example.test'): array
    {
        return ['party' => 'complainant:'.$case->complainants()->firstOrFail()->id,
            'email' => $email, 'contact_verified' => '1', 'notification_consent' => '1'];
    }

    private function contact(BlotterCase $case): CaseNotificationContact
    {
        $this->post(route('cases.notifications.store', $case), $this->contactData($case))->assertSessionHasNoErrors();

        return $case->notificationContacts()->firstOrFail();
    }

    private function enableMail(): void
    {
        config(['case_notifications.enabled' => true, 'case_notifications.mailer' => 'smtp']);
    }
}
