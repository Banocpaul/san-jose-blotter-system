@if($case->mediation_requested_at)
    <div class="card shadow-sm mb-3"><div class="card-body">
        <h6>Councilor Referral for Mediation</h6>
        @if($case->case_stage === \App\Enums\CaseStage::UnderAssessment && $case->record_status === \App\Enums\RecordStatus::Open)
            <div class="alert alert-info mb-2">Awaiting Secretary mediation scheduling.</div>
        @endif
        <div style="white-space: pre-line;">{{ $case->mediation_request_reason }}</div>
        <small class="text-muted">Submitted {{ $case->mediation_requested_at->timezone('Asia/Manila')->format('M d, Y h:i A') }} (Philippine time)</small>
    </div></div>
@elseif($case->record_status === \App\Enums\RecordStatus::Open && auth()->user()->can('manageWorkflow', $case))
    <div class="alert alert-secondary">Awaiting the assigned Councilor's referral for mediation. Hearing scheduling is unavailable until the referral is submitted.</div>
@endif
@if($case->pangkat_members)
    <div class="card shadow-sm mb-3"><div class="card-body">
        <strong>Recorded Pangkat members</strong>
        <ul class="mb-0">@foreach($case->pangkat_members as $member)<li>{{ $member['name'] }}</li>@endforeach</ul>
    </div></div>
@endif
@if($case->disposition)
    <div class="card shadow-sm mb-3"><div class="card-body">
        <strong>Disposition: {{ $case->disposition }}</strong>
        <p class="mb-1">{{ $case->disposition_reason }}</p>
        @if($case->referral_agency)<p class="mb-1">Referral agency: {{ $case->referral_agency }}</p>@endif
        @if($case->further_action_documentation)<p class="mb-1">Further-action documentation: {{ $case->further_action_documentation }}</p>@endif
        <small class="text-muted">Recorded {{ $case->disposed_at?->format('M d, Y h:i A') }}</small>
    </div></div>
@endif
@if(! $case->disposition && $case->further_action_documentation)
    <div class="alert alert-info"><strong>Further-action documentation:</strong> {{ $case->further_action_documentation }}</div>
@endif
