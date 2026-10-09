<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseDispositionController extends Controller
{
    public function assess(Request $request, BlotterCase $blotter): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        DB::transaction(function () use ($blotter) {
            $case = BlotterCase::whereKey($blotter->id)->lockForUpdate()->firstOrFail();
            $case->requireOpenStage([CaseStage::New]);
            $old = $case->only(['case_stage', 'record_status', 'status']);
            $case->update(['case_stage' => CaseStage::UnderAssessment, 'status' => CaseStatus::UnderInvestigation]);
            AuditLogService::log(action: 'assessment_started', module: 'Case Management',
                description: "Assessment started for {$case->reference_number}.", auditable: $case,
                oldValues: $old, newValues: $case->only(['case_stage', 'record_status', 'status']));
        });

        return back()->with('success', 'Case review started. Assign a Councilor or record the assessment decision.');
    }

    public function constitutePangkat(Request $request, BlotterCase $blotter): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        $data = $request->validate([
            'pangkat_member_ids' => ['required_without:pangkat_member_names', 'array', 'size:3'],
            'pangkat_member_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'pangkat_member_names' => ['required_without:pangkat_member_ids', 'array', 'size:2'],
            'pangkat_member_names.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'pangkat_presiding_member_id' => ['required_with:pangkat_member_names', 'integer', 'exists:users,id'],
        ]);
        if (isset($data['pangkat_member_names'])) {
            $chair = User::with('role')->findOrFail($data['pangkat_presiding_member_id']);
            if (! $chair->is_active || $chair->role?->slug !== 'lupon') {
                throw ValidationException::withMessages(['pangkat_presiding_member_id' => 'Select an active Lupon member to preside.']);
            }
            $members = collect([['id' => $chair->id, 'name' => $chair->name]])->concat(
                array_map(fn ($name) => ['id' => null, 'name' => trim($name)], $data['pangkat_member_names']));
            if ($members->map(fn ($member) => mb_strtolower(trim($member['name'])))->unique()->count() !== 3) {
                throw ValidationException::withMessages(['pangkat_member_names' => 'Record three distinct Pangkat members.']);
            }
        } else {
            $users = User::with('role')->whereIn('id', $data['pangkat_member_ids'])->get();
            if ($users->count() !== 3 || $users->contains(fn ($user) => ! $user->is_active || $user->role?->slug !== 'lupon')) {
                throw ValidationException::withMessages(['pangkat_member_ids' => 'Select three distinct active Lupon members.']);
            }
            $members = $users->map(fn ($user) => ['id' => $user->id, 'name' => $user->name]);
        }
        DB::transaction(function () use ($blotter, $members) {
            $case = BlotterCase::whereKey($blotter->id)->lockForUpdate()->firstOrFail();
            $case->requireOpenStage([CaseStage::ForMediation, CaseStage::ForPangkatConciliation]);
            $latest = $case->mediationSessions()->latest('hearing_number')->first();
            $legacyPangkat = $case->case_stage === CaseStage::ForPangkatConciliation;
            $failedMediation = $latest && $latest->status === 'Completed'
                && ($latest->proceeding_type ?: 'Mediation') === 'Mediation' && $latest->outcome?->outcome === 'No Agreement';
            if ($case->pangkat_constituted_at || $case->mediationSessions()->where('status', 'Scheduled')->exists()
                || (! $legacyPangkat && ! $failedMediation)) {
                throw ValidationException::withMessages(['pangkat' => 'Record unsuccessful mediation before constituting the Pangkat.']);
            }
            $case->update(['pangkat_members' => $members->all(),
                'pangkat_constituted_at' => now()]);
            AuditLogService::log(action: 'pangkat_constituted', module: 'Case Management',
                description: "Pangkat members recorded for {$case->reference_number}.", auditable: $case,
                newValues: $case->only(['pangkat_members', 'pangkat_constituted_at']));
        });

        return back()->with('success', 'Pangkat constituted. Schedule conciliation and prepare the required notices.');
    }

    public function recordDocumentation(Request $request, BlotterCase $blotter): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        $data = $request->validate(['further_action_documentation' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($blotter, $data) {
            $case = BlotterCase::whereKey($blotter->id)->lockForUpdate()->firstOrFail();
            $case->requireOpenStage([CaseStage::ForFurtherActionCfa]);
            $old = $case->only(['further_action_documentation']);
            $case->update($data);
            AuditLogService::log(action: 'further_action_documented', module: 'Case Management',
                description: "Further-action documentation recorded for {$case->reference_number}.", auditable: $case,
                oldValues: $old, newValues: $data);
        });

        return back()->with('success', 'Further-action documentation saved. Record the final disposition when ready.');
    }

    public function resumeFurtherAction(Request $request, BlotterCase $blotter): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        $data = $request->validate(['resume_reason' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($blotter, $data) {
            $case = BlotterCase::whereKey($blotter->id)->lockForUpdate()->firstOrFail();
            $latest = $case->mediationSessions()->latest('hearing_number')->first();
            if ($case->case_stage !== CaseStage::ForFurtherActionCfa || $case->record_status !== RecordStatus::Closed
                || $case->disposition || ! $latest || $latest->proceeding_type !== 'Pangkat Conciliation'
                || $latest->outcome?->outcome !== 'No Agreement') {
                throw ValidationException::withMessages(['workflow' => 'Only a legacy case prematurely closed after unsuccessful Pangkat can resume further action.']);
            }
            $old = $case->only(['status', 'record_status', 'closed_at']);
            $case->update(['status' => CaseStatus::ForMediation, 'record_status' => RecordStatus::Open, 'closed_at' => null]);
            AuditLogService::log(action: 'further_action_resumed', module: 'Case Management',
                description: "Further-action processing resumed for {$case->reference_number}.", auditable: $case,
                oldValues: $old, newValues: $case->only(['status', 'record_status', 'closed_at']) + $data);
        });

        return back()->with('success', 'Further-action processing resumed. Historical proceedings and the previous closure are retained in the audit trail.');
    }

    public function dispose(Request $request, BlotterCase $blotter): RedirectResponse
    {
        $this->authorize('manageWorkflow', $blotter);
        $data = $request->validate([
            'disposition' => ['required', Rule::in(['Referred', 'Dismissed', 'CFA Issued', 'Closed'])],
            'disposition_reason' => ['required', 'string', 'max:10000'],
            'referral_agency' => ['required_if:disposition,Referred', 'nullable', 'string', 'max:255'],
            'further_action_documentation' => ['nullable', 'string', 'max:10000'],
        ]);
        DB::transaction(function () use ($blotter, $data) {
            $case = BlotterCase::whereKey($blotter->id)->lockForUpdate()->firstOrFail();
            $case->requireOpenStage([CaseStage::UnderAssessment, CaseStage::ForFurtherActionCfa]);
            if ($case->case_stage === CaseStage::UnderAssessment && ! in_array($data['disposition'], ['Referred', 'Dismissed'], true)) {
                throw ValidationException::withMessages(['disposition' => 'Assessment can end with a referral or dismissal.']);
            }
            $documentation = $data['further_action_documentation'] ?? $case->further_action_documentation;
            if ($case->case_stage === CaseStage::ForFurtherActionCfa && blank($documentation)) {
                throw ValidationException::withMessages(['further_action_documentation' => 'Record the required further-action documentation before final disposition.']);
            }
            $old = $case->only(['case_stage', 'record_status', 'status']);
            $case->update(array_merge($data, [
                'further_action_documentation' => $documentation,
                'status' => $data['disposition'] === 'Dismissed' ? CaseStatus::Dismissed : CaseStatus::Referred,
                'case_stage' => CaseStage::Closed, 'record_status' => RecordStatus::Closed,
                'closed_at' => now(), 'disposed_at' => now(), 'disposed_by' => auth()->id(),
            ]));
            $case->assignments()->whereNull('completed_at')->update(['completed_at' => now()]);
            AuditLogService::log(action: 'final_disposition_recorded', module: 'Case Management',
                description: "{$data['disposition']} recorded for {$case->reference_number}.", auditable: $case,
                oldValues: $old, newValues: $case->only(['case_stage', 'record_status', 'status', 'disposition',
                    'disposition_reason', 'referral_agency', 'further_action_documentation', 'disposed_by', 'disposed_at']));
        });

        return back()->with('success', 'Final disposition recorded and case closed.');
    }
}
