<?php

namespace App\Jobs;

use App\Mail\CasePartyUpdate;
use App\Models\CaseNotificationDelivery;
use App\Services\CaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendCasePartyNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public string $deliveryId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('case-notification:'.$this->deliveryId))->releaseAfter(30)->expireAfter(120)];
    }

    public function handle(CaseNotificationService $notifications): void
    {
        $delivery = CaseNotificationDelivery::with('contact.blotterCase')->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['submitted', 'canceled'], true)) {
            return;
        }
        $contact = $delivery->contact;
        if (! $contact?->enabled || $contact->revision !== $delivery->contact_revision || ! $contact->party()) {
            $delivery->update(['status' => 'canceled']);

            return;
        }
        if (! $notifications->isConfigured()) {
            $delivery->update(['status' => 'not_configured']);

            return;
        }
        try {
            Mail::mailer(config('case_notifications.mailer'))->to($delivery->recipient_email)
                ->send(new CasePartyUpdate($delivery->message));
        } catch (Throwable) {
            $delivery->update(['status' => 'retrying']);
            throw new RuntimeException('Case notification could not be submitted to the mail service.');
        }
        $delivery->update(['status' => 'submitted', 'submitted_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        CaseNotificationDelivery::whereKey($this->deliveryId)
            ->whereNotIn('status', ['submitted', 'canceled'])->update(['status' => 'failed']);
    }
}
