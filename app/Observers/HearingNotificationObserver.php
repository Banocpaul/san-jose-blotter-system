<?php

namespace App\Observers;

use App\Models\MediationSession;
use App\Services\CaseNotificationService;

class HearingNotificationObserver
{
    public function created(MediationSession $session): void
    {
        if ($session->status === 'Scheduled') {
            app(CaseNotificationService::class)->hearingScheduled($session);
        }
    }

    public function updated(MediationSession $session): void
    {
        if ($session->status === 'Scheduled' && $session->wasChanged(['scheduled_date', 'scheduled_time', 'venue'])) {
            app(CaseNotificationService::class)->hearingScheduled($session);
        } elseif ($session->wasChanged('status') && $session->status === 'Cancelled' && $session->blotterCase) {
            app(CaseNotificationService::class)->caseUpdated($session->blotterCase);
        }
    }
}
