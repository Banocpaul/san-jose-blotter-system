<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Services\AuditLogService;
use App\Services\CaseJurisdictionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseJurisdictionController extends Controller
{
    public function update(Request $request, BlotterCase $blotter, CaseJurisdictionService $jurisdiction): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['within_authority', 'for_referral', 'legal_review'])],
            'authority_basis' => ['required', 'string', 'min:10', 'max:5000'],
            'venue_basis' => ['required', 'string', 'min:10', 'max:3000'],
            'referral_agency' => ['required_if:decision,for_referral', 'nullable', 'string', 'max:255'],
            'exclusions_reviewed' => ['accepted'],
        ]);

        DB::transaction(function () use ($blotter, $data, $jurisdiction): void {
            $case = BlotterCase::whereKey($blotter->id)->lockForUpdate()->firstOrFail();
            if ($case->record_status !== RecordStatus::Open || $case->mediationSessions()->where('status', 'Scheduled')->exists()) {
                throw ValidationException::withMessages(['jurisdiction' => 'Review jurisdiction on an open case without an active scheduled hearing.']);
            }
            $old = $case->jurisdictionAssessment?->toArray() ?? [];
            $assessment = $case->jurisdictionAssessment()->updateOrCreate([], [
                'decision' => $data['decision'],
                'authority_basis' => $data['authority_basis'],
                'venue_basis' => $data['venue_basis'],
                'referral_agency' => $data['decision'] === 'for_referral' ? $data['referral_agency'] : null,
                'facts_fingerprint' => $jurisdiction->fingerprint($case),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            AuditLogService::log(action: 'jurisdiction_reviewed', module: 'Case Management',
                description: "Jurisdiction review recorded for {$case->reference_number}.", auditable: $case,
                oldValues: $old, newValues: $assessment->toArray());
        });

        return back()->with('success', 'Jurisdiction review saved. Referral decisions still require the final disposition workflow.');
    }
}
