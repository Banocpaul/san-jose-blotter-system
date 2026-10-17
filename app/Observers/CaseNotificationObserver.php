<?php

namespace App\Observers;

use App\Enums\CaseStage;
use App\Models\BlotterCase;
use App\Services\CaseNotificationService;

class CaseNotificationObserver
{
    public function updated(BlotterCase $case): void
    {
        if ($case->wasChanged(['case_stage', 'record_status'])
            && ! in_array($case->case_stage, [CaseStage::ForMediation, CaseStage::ForPangkatConciliation], true)) {
            app(CaseNotificationService::class)->caseUpdated($case);
        }
    }
}
