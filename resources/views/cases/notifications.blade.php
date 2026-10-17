@extends('layouts.app')
@section('title', 'Party Notifications')
@section('page-title', 'Party Notifications')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h3 class="mb-1">Party Notifications</h3><p class="text-muted mb-0">{{ $case->reference_number }}</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('cases.show', $case) }}">Back to Case</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(! $configured)<div class="alert alert-warning">Email delivery is not enabled or configured. Updates will be recorded as <strong>Not configured</strong> until the administrator sets up the mail service.</div>@endif
<div class="alert alert-info small">Optional email updates supplement official notices and summons. Verify each recipient's address directly and record their agreement before enabling updates. Messages contain a reference and a short update, without allegations or other parties' names.</div>
<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white"><strong>Enable / Update Recipient</strong></div><div class="card-body">
        <form method="POST" action="{{ route('cases.notifications.store', $case) }}">
            @csrf
            <label for="noticeParty" class="form-label">Case party *</label>
            <select id="noticeParty" name="party" class="form-select mb-3" required>
                <option value="">Select complainant, respondent, or witness</option>
                @foreach($parties as $party)<option value="{{ $party['key'] }}" @selected(old('party') === $party['key'])>{{ $party['label'] }}</option>@endforeach
            </select>
            <label for="noticeEmail" class="form-label">Verified email address *</label>
            <input id="noticeEmail" name="email" type="email" maxlength="255" value="{{ old('email') }}" class="form-control mb-3" required autocomplete="off">
            <div class="form-check mb-2"><input id="noticeVerified" name="contact_verified" type="checkbox" value="1" class="form-check-input" required><label for="noticeVerified" class="form-check-label">I verified that this address belongs to the selected party.</label></div>
            <div class="form-check mb-3"><input id="noticeConsent" name="notification_consent" type="checkbox" value="1" class="form-check-input" required><label for="noticeConsent" class="form-check-label">The party agreed to optional case updates at this address after the privacy notice was explained.</label></div>
            <button class="btn btn-primary" @disabled($parties->isEmpty())>Save Verified Contact</button>
        </form>
    </div></div></div>
    <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white"><strong>Recipient Preferences</strong></div><div class="card-body">
        @forelse($contacts as $contact)
            <div class="border rounded p-3 mb-2">
                <div class="fw-semibold">{{ $parties->firstWhere('key', $contact->party_type.':'.$contact->party_id)['label'] ?? 'Removed party' }}</div>
                <div class="small text-muted">{{ $contact->maskedEmail() }} · {{ $contact->enabled ? 'Enabled' : 'Disabled' }}</div>
                <div class="small text-muted">Agreement recorded {{ $contact->consent_recorded_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</div>
                @if($contact->enabled)<form method="POST" action="{{ route('cases.notifications.disable', [$case, $contact]) }}" class="mt-2">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Disable Updates</button></form>@endif
            </div>
        @empty<p class="text-muted mb-0">No verified notification contacts yet.</p>@endforelse
    </div></div></div>
</div>
<div class="card shadow-sm"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Notification History</strong><a class="btn btn-sm btn-outline-secondary" href="{{ route('cases.notifications.index', $case) }}">Refresh Status</a></div>
    <div class="card-body">
        <p class="small text-muted">Queued updates require a running notification worker. Submitted means the mail service accepted the request; recipient delivery and reading are not confirmed.</p>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Created (PH)</th><th>Recipient</th><th>Update</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @forelse($deliveries as $delivery)<tr>
                <td>{{ $delivery->created_at->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                <td>{{ ucfirst($delivery->contact->party_type) }} · {{ $delivery->maskedRecipient() }}</td>
                <td>{{ $delivery->event === 'hearing_scheduled' ? 'Hearing scheduled' : 'Case updated' }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $delivery->status)) }}</td>
                <td>@if($configured && $delivery->contact->enabled && $delivery->contact_revision === $delivery->contact->revision && (in_array($delivery->status, ['failed', 'not_configured'], true) || ($delivery->status === 'queued' && $delivery->updated_at->lt(now()->subMinutes(5)))))
                    <form method="POST" action="{{ route('cases.notifications.retry', [$case, $delivery]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry</button></form>
                @endif</td>
            </tr>@empty<tr><td colspan="5" class="text-muted">No updates have been recorded.</td></tr>@endforelse
        </tbody></table></div>
        {{ $deliveries->links() }}
    </div>
</div>
@endsection
