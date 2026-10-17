<?php

namespace App\Services;

use App\Jobs\SendCasePartyNotification;
use App\Models\BlotterCase;
use App\Models\CaseNotificationDelivery;
use App\Models\MediationSession;
use Illuminate\Support\Str;

class CaseNotificationService
{
    public function isConfigured(): bool
    {
        $mailer = config('case_notifications.mailer');
        $transport = config("mail.mailers.{$mailer}.transport");

        return config('case_notifications.enabled') && in_array($transport,
            ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true);
    }

    public function caseUpdated(BlotterCase $case): void
    {
        $this->queue($case, 'case_updated',
            'An update has been recorded for your case. Contact the Barangay San Jose office for the details.');
    }

    public function hearingScheduled(MediationSession $session): void
    {
        $case = $session->blotterCase;
        if ($case) {
            $this->queue($case, 'hearing_scheduled', 'A hearing for your case reference is scheduled for '
                .$session->scheduled_date->format('F j, Y').' at '.substr($session->scheduled_time, 0, 5)
                .' (Philippine time), at '.$session->venue.'. Contact the barangay office if you need assistance.');
        }
    }

    private function queue(BlotterCase $case, string $event, string $message): void
    {
        foreach ($case->notificationContacts()->where('enabled', true)->get() as $contact) {
            if (! $contact->party()) {
                continue;
            }
            $delivery = $contact->deliveries()->create([
                'id' => (string) Str::uuid(),
                'contact_revision' => $contact->revision,
                'recipient_email' => $contact->email,
                'event' => $event,
                'message' => 'Reference: '.$case->reference_number."\n\n".$message,
                'status' => $this->isConfigured() ? 'queued' : 'not_configured',
            ]);
            if ($delivery->status === 'queued') {
                $this->dispatch($delivery);
            }
        }
    }

    public function dispatch(CaseNotificationDelivery $delivery): void
    {
        SendCasePartyNotification::dispatch($delivery->id)
            ->onQueue(config('case_notifications.queue'))->afterCommit();
    }
}
