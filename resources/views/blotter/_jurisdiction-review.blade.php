@php
    $review = $case->jurisdictionAssessment;
    $jurisdictionApproved = app(\App\Services\CaseJurisdictionService::class)->isApproved($case);
    $reviewLabels = ['within_authority' => 'Within settlement authority', 'for_referral' => 'For referral', 'legal_review' => 'Needs legal review'];
    $reviewStatus = $review?->decision === 'within_authority' && ! $jurisdictionApproved
        ? 'Review outdated' : ($reviewLabels[$review?->decision] ?? 'Not reviewed');
    $canReviewJurisdiction = request()->routeIs('cases.show') && auth()->user()->can('manageWorkflow', $case);
@endphp
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Settlement Authority &amp; Venue</strong>
        <span class="badge {{ $jurisdictionApproved ? 'text-bg-success' : 'text-bg-warning' }}">
            {{ $reviewStatus }}
        </span>
    </div>
    <div class="card-body">
        <p class="small text-muted">An incident category is used for recording and reporting. The Captain or Secretary must separately review legal coverage, the parties' actual residence, proper venue, and applicable exclusions before mediation.</p>
        @if($review)
            <dl class="row small mb-2">
                <dt class="col-sm-3">Authority basis</dt><dd class="col-sm-9 text-break">{{ $review->authority_basis }}</dd>
                <dt class="col-sm-3">Venue basis</dt><dd class="col-sm-9 text-break">{{ $review->venue_basis }}</dd>
                @if($review->referral_agency)<dt class="col-sm-3">Suggested referral</dt><dd class="col-sm-9">{{ $review->referral_agency }}</dd>@endif
                <dt class="col-sm-3">Reviewed by</dt><dd class="col-sm-9">{{ $review->reviewer?->name ?? 'Former authorized official' }} · {{ $review->reviewed_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd>
            </dl>
        @endif
        @if(! $jurisdictionApproved)
            <div class="alert alert-warning small">Mediation requires a current approval for barangay settlement authority and venue. Changed case facts require a new review. Referral and legal-review decisions do not authorize mediation.</div>
        @endif
        <p class="small mb-2">Review <a href="https://lawphil.net/statutes/repacts/ra1991/ra_7160_1991.html" target="_blank" rel="noopener noreferrer">RA 7160, Sections 407–409</a> and applicable special laws. Protection proceedings under RA 9262 require separate handling; a family-dispute label does not establish mediation eligibility.</p>
        @if($canReviewJurisdiction)
            <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('cases.notifications.index', $case) }}">Party Notifications</a>
            @if($case->record_status === \App\Enums\RecordStatus::Open && ! $case->mediationSessions->contains('status', 'Scheduled'))
                <details @if($errors->hasAny(['decision', 'authority_basis', 'venue_basis', 'exclusions_reviewed', 'jurisdiction'])) open @endif>
                    <summary class="fw-semibold mb-3">Record / update the official review</summary>
                    <form method="POST" action="{{ route('cases.jurisdiction', $case) }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="jurisdictionDecision" class="form-label">Decision *</label>
                                <select id="jurisdictionDecision" name="decision" class="form-select" required>
                                    <option value="">Select review decision</option>
                                    @foreach($reviewLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('decision', $review?->decision) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="jurisdictionReferral" class="form-label">Referral agency (required for referral)</label>
                                <input id="jurisdictionReferral" name="referral_agency" class="form-control" maxlength="255" value="{{ old('referral_agency', $review?->referral_agency) }}">
                            </div>
                            <div class="col-md-6">
                                <label for="jurisdictionAuthority" class="form-label">Authority / exclusion findings and legal basis *</label>
                                <textarea id="jurisdictionAuthority" name="authority_basis" class="form-control" rows="3" minlength="10" maxlength="5000" required>{{ old('authority_basis', $review?->authority_basis) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="jurisdictionVenue" class="form-label">Actual residence / venue findings *</label>
                                <textarea id="jurisdictionVenue" name="venue_basis" class="form-control" rows="3" minlength="10" maxlength="3000" required>{{ old('venue_basis', $review?->venue_basis) }}</textarea>
                            </div>
                            <div class="col-12 form-check ms-2">
                                <input id="jurisdictionExclusions" name="exclusions_reviewed" value="1" type="checkbox" class="form-check-input" required>
                                <label for="jurisdictionExclusions" class="form-check-label">I reviewed applicable authority, venue, and special-law exclusions. This records my official assessment.</label>
                            </div>
                            <div class="col-12"><button class="btn btn-primary">Save Jurisdiction Review</button></div>
                        </div>
                    </form>
                </details>
            @endif
        @endif
    </div>
</div>
