@extends('layouts.app')
@section('title', 'SLA Settings')
@section('page-title', 'SLA Settings')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h3 class="mb-1">SLA Settings</h3><p class="text-muted mb-0">Set processing targets for Barangay San Jose's workflow stages.</p></div>
    <a href="{{ route('analytics.index', ['tab' => 'bottlenecks']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> SLA & Bottlenecks</a>
</div>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>Review these settings:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="alert alert-light border mb-4">Saving recalculates stage deadlines and SLA indicators for current open cases using their recorded start dates. These settings control operational monitoring targets. Recorded Pangkat extensions add 15 calendar days.</div>
<form method="POST" action="{{ route('settings.sla.update') }}">
    @csrf @method('PUT')
    <input type="hidden" name="revision" value="{{ $policy['revision'] }}">
    <div class="card mb-4"><div class="card-header"><strong>Workflow Stage Targets</strong></div><div class="card-body">
        <p class="small text-muted">Choose 1–365 days per stage. Working days exclude weekends and the non-working dates below. Clock-start events are fixed so the same recorded dates are used consistently.</p>
        @foreach($stages as $index => $stage)
            @php($target = $policy['targets'][$stage])
            <div class="row g-3 align-items-end {{ !$loop->last ? 'pb-3 mb-3 border-bottom' : '' }}">
                <div class="col-md-5"><strong>{{ $stage }}</strong><div class="small text-muted mt-1">Starts at: {{ $target['start'] }}</div></div>
                <div class="col-sm-6 col-md-3"><label for="stage_days_{{ $index }}" class="form-label">{{ $stage }} target days</label>
                    <input id="stage_days_{{ $index }}" name="targets[{{ $index }}][days]" type="number" min="1" max="365" step="1" required value="{{ old('targets.'.$index.'.days', $target['days']) }}" class="form-control @error('targets.'.$index.'.days') is-invalid @enderror"></div>
                <div class="col-sm-6 col-md-4"><label for="stage_unit_{{ $index }}" class="form-label">Day type</label><select id="stage_unit_{{ $index }}" name="targets[{{ $index }}][unit]" class="form-select" required>
                    <option value="working" @selected(old('targets.'.$index.'.unit', $target['unit']) === 'working')>Working days</option>
                    <option value="calendar" @selected(old('targets.'.$index.'.unit', $target['unit']) === 'calendar')>Calendar days</option>
                </select></div>
            </div>
        @endforeach
        <p class="small text-muted mt-3 mb-0">The starting recommendations are 1 / 3 working days for intake and assessment, 15 calendar days each for mediation and Pangkat, and 3 working days for further action. Mediation and Pangkat clocks start at confirmed meetings; changing an analysis target does not amend statutory deadlines or approve an extension.</p>
    </div></div>
    <div class="row g-4 mb-4">
        <div class="col-lg-5"><div class="card h-100"><div class="card-header"><strong>Near-SLA Warning</strong></div><div class="card-body">
            <label for="near_percent" class="form-label">Warn after this percentage of allowed time is used</label>
            <div class="input-group mb-3"><input id="near_percent" name="near_percent" type="number" min="1" max="99" step="1" value="{{ old('near_percent', $policy['near_percent']) }}" required class="form-control @error('near_percent') is-invalid @enderror"><span class="input-group-text">%</span></div>
            <p class="small text-muted mb-0">For example, 80% starts the warning after 12 days of a 15-calendar-day target. Within SLA is below your threshold; Near SLA runs from the threshold to the deadline; Beyond SLA starts after the deadline passes.</p>
        </div></div></div>
        <div class="col-lg-7"><div class="card h-100"><div class="card-header"><strong>Non-Working Dates</strong></div><div class="card-body">
            <label for="non_working_dates" class="form-label">Applicable holidays and office non-working dates</label>
            <textarea id="non_working_dates" name="non_working_dates" maxlength="6000" rows="6" class="form-control font-monospace" placeholder="2026-12-25&#10;2026-12-30" aria-describedby="holiday_help">{{ is_string(old('non_working_dates')) ? old('non_working_dates') : implode("\n", $policy['non_working_dates']) }}</textarea>
            <p id="holiday_help" class="small text-muted mt-2 mb-0">Use YYYY-MM-DD, one date per line. Duplicate dates are saved once. Working-day targets skip these dates and weekends; calendar-day targets include them.</p>
        </div></div></div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="small text-muted">@if($settings->updated_by)Last saved by {{ $settings->updatedBy?->name ?? 'Unknown user' }}@elseif($policy['revision'] > 1)
            Last saved · user unavailable
        @else
            Default settings initialized
        @endif · {{ $settings->updated_at?->setTimezone('Asia/Manila')->format('M d, Y · h:i A') }} PHT</div>
        <div class="d-flex flex-wrap gap-2"><a href="{{ route('settings.sla.edit') }}" class="btn btn-outline-secondary">Reload Current Settings</a><button type="submit" class="btn btn-primary" @disabled($errors->has('revision'))><i class="bi bi-check2" aria-hidden="true"></i> Save SLA Settings</button></div>
    </div>
</form>
@endsection
