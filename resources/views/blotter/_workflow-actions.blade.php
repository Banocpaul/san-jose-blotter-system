@php
    $open = $case->record_status === \App\Enums\RecordStatus::Open;
    $stage = $case->case_stage;
    $manager = auth()->user()->can('manageWorkflow', $case);
    $latest = $case->mediationSessions->sortByDesc('hearing_number')->first();
    $failedMediation = $latest && ($latest->proceeding_type ?: 'Mediation') === 'Mediation'
        && $latest->outcome?->outcome === 'No Agreement';
    $needsPangkat = $failedMediation || $case->pangkat_constituted_at || $stage === \App\Enums\CaseStage::ForPangkatConciliation;
    $proceeding = $needsPangkat ? 'Pangkat Conciliation' : 'Mediation';
    $canSchedule = $open && ! $hasActiveScheduledHearing &&
        (($stage === \App\Enums\CaseStage::UnderAssessment && $case->mediation_requested_at)
        || in_array($stage, [\App\Enums\CaseStage::ForMediation, \App\Enums\CaseStage::ForPangkatConciliation], true));
@endphp

@if($open && $manager && $stage === \App\Enums\CaseStage::New)
    <form method="POST" action="{{ route('cases.assess', $case) }}" class="mb-3">
        @csrf
        <p>Review the complaint and begin assessment before deciding the next action.</p>
        <button class="btn btn-primary">Start Assessment</button>
    </form>
@endif

@if($open && $stage === \App\Enums\CaseStage::UnderAssessment && ! $case->mediation_requested_at && auth()->user()->can('referToMediation', $case))
    <form method="POST" action="{{ route('blotter.mediation.refer', $case) }}" class="mb-3">
        @csrf
        <label class="form-label">Assessment findings / reason to proceed to mediation *</label>
        <textarea name="mediation_request_reason" class="form-control mb-2" rows="3" maxlength="10000" required>{{ old('mediation_request_reason') }}</textarea>
        <p class="small text-muted">This submits the assessment to the Secretary. A hearing starts only after an explicit date, time, venue and presiding officer are selected.</p>
        <button class="btn btn-info">Submit for Mediation Scheduling</button>
    </form>
@endif

@if($open && $manager && $needsPangkat && ! $case->pangkat_constituted_at && ! $hasActiveScheduledHearing)
    <form method="POST" action="{{ route('cases.pangkat', $case) }}" class="mb-3">
        @csrf
        <h6>Constitute Pangkat</h6>
        <label class="form-label">Presiding Pangkat member *</label>
        <select name="pangkat_presiding_member_id" class="form-select mb-2" required>
            <option value="">Select active Lupon member</option>
            @foreach($luponMembers->where('role.slug', 'lupon') as $member)
                <option value="{{ $member->id }}" @selected(old('pangkat_presiding_member_id') == $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @for($index = 0; $index < 2; $index++)
            <label class="form-label">Other Pangkat member {{ $index + 1 }} *</label>
            <input name="pangkat_member_names[]" value="{{ old('pangkat_member_names.'.$index) }}" maxlength="255" class="form-control mb-2" required>
        @endfor
        <p class="small text-muted">Record the other members' full names. They do not need system accounts; hearing access follows the assigned presiding officer.</p>
        <button class="btn btn-warning">Record Pangkat Members</button>
    </form>
@endif

@if($canSchedule && $manager && (! $needsPangkat || $case->pangkat_constituted_at))
    <form method="POST" action="{{ route('blotter.mediation.schedule', $case) }}" class="mb-3">
        @csrf
        <h6>Schedule {{ $proceeding }} Hearing</h6>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Hearing Date *</label>
                <input type="date" name="scheduled_date" value="{{ old('scheduled_date') }}" min="{{ now('Asia/Manila')->toDateString() }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Hearing Time (Philippine time) *</label>
                <input type="time" name="scheduled_time" value="{{ old('scheduled_time') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Venue *</label>
                <input name="venue" value="{{ old('venue', 'Barangay Hall') }}" maxlength="255" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">{{ $needsPangkat ? 'Presiding Pangkat Member' : 'Punong Barangay / Lupon Chairman' }} *</label>
                <select name="lupon_member_id" class="form-select" required>
                    <option value="">Select presiding officer</option>
                    @foreach($luponMembers as $member)
                        @if(! $needsPangkat || in_array($member->id, array_column($case->pangkat_members ?? [], 'id')))
                            <option value="{{ $member->id }}" @selected(old('lupon_member_id') == $member->id)>{{ $member->name }}</option>
                        @endif
                    @endforeach
                </select></div>
            <div class="col-12"><label class="form-label">Proceeding notes</label>
                <textarea name="mediation_notes" class="form-control" rows="2">{{ old('mediation_notes') }}</textarea>
                <p class="form-text">Scheduling creates attendance and required notice records for both parties. Prepare and serve notices below.</p></div>
            <div class="col-12"><button class="btn btn-primary">Schedule {{ $proceeding }} Hearing</button></div>
        </div>
    </form>
@endif
@if($hasActiveScheduledHearing && $open)<div class="alert alert-info">A hearing is scheduled. Record attendance, notice delivery and the proceeding outcome below.</div>@endif

@if($manager && ! $open && $stage === \App\Enums\CaseStage::ForFurtherActionCfa && ! $case->disposition && $latest?->proceeding_type === 'Pangkat Conciliation' && $latest?->outcome?->outcome === 'No Agreement')
    <form method="POST" action="{{ route('cases.resume-further-action', $case) }}" class="mb-3">
        @csrf
        <p>This older case was closed after unsuccessful Pangkat without a final disposition.</p>
        <label class="form-label">Reason to resume further-action processing *</label>
        <textarea name="resume_reason" class="form-control mb-2" required>{{ old('resume_reason') }}</textarea>
        <button class="btn btn-warning">Resume Further-Action Processing</button>
    </form>
@endif
@if($open && $manager && $stage === \App\Enums\CaseStage::ForFurtherActionCfa)
    <form method="POST" action="{{ route('cases.documentation', $case) }}" class="mb-3">
        @csrf
        <h6>Prepare / Record Further-Action Documentation</h6>
        <label class="form-label">Documentation / document reference *</label>
        <textarea name="further_action_documentation" class="form-control mb-2" rows="3" required>{{ old('further_action_documentation', $case->further_action_documentation) }}</textarea>
        <button class="btn btn-primary">Save Further-Action Documentation</button>
    </form>
@endif

@if($open && $manager && in_array($stage, [\App\Enums\CaseStage::UnderAssessment, \App\Enums\CaseStage::ForFurtherActionCfa], true))
    <form method="POST" action="{{ route('cases.dispose', $case) }}" class="border-top pt-3 mt-3">
        @csrf
        <h6>{{ $stage === \App\Enums\CaseStage::ForFurtherActionCfa ? 'Further Action / CFA and Final Disposition' : 'Assessment Referral or Dismissal' }}</h6>
        <label class="form-label">Disposition *</label>
        <select name="disposition" class="form-select mb-2" required>
            <option value="">Select disposition</option>
            @foreach($stage === \App\Enums\CaseStage::ForFurtherActionCfa ? ['Referred', 'Dismissed', 'CFA Issued', 'Closed'] : ['Referred', 'Dismissed'] as $choice)
                <option value="{{ $choice }}" @selected(old('disposition') === $choice)>{{ $choice }}</option>
            @endforeach
        </select>
        <label class="form-label">Reason *</label>
        <textarea name="disposition_reason" class="form-control mb-2" rows="3" required>{{ old('disposition_reason') }}</textarea>
        <label class="form-label">Referral agency (required for referral)</label>
        <input name="referral_agency" value="{{ old('referral_agency') }}" maxlength="255" class="form-control mb-2">
        @if($stage === \App\Enums\CaseStage::ForFurtherActionCfa)
            <label class="form-label">Required further-action documentation / document reference *</label>
            <textarea name="further_action_documentation" class="form-control mb-2" rows="3" required>{{ old('further_action_documentation', $case->further_action_documentation) }}</textarea>
        @endif
        <button class="btn btn-outline-danger" onclick="return confirm('Record this final disposition and close the case?')">Record Disposition and Close</button>
    </form>
@endif
@if(! $open)<div class="alert alert-secondary mb-0">The case is {{ $case->record_status->value }}. Proceeding history is retained below.</div>@endif
@if($case->caseResolution && $manager && $case->caseResolution->status === 'Active')
    <form method="POST" action="{{ route('settlements.complete', $case->caseResolution) }}" class="mt-3">
        @csrf
        @method('PATCH')
        <button class="btn btn-success">Complete Settlement Record</button>
    </form>
@endif
