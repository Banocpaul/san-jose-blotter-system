@extends('layouts.app')

@section('title', 'Case Management')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Case Management</h3>
        <div class="text-muted">
            Central tracking of case stage, status, assignment, and progress.
        </div>
    </div>

    @can('create', App\Models\BlotterCase::class)
        <a href="{{ route('blotter.create') }}" class="btn btn-primary">
            <i class="bi bi-file-earmark-plus me-1"></i>
            New Blotter Case
        </a>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="row g-3 mb-4">
    @foreach($kpiStages as $stage)
        <div class="col-12 col-sm-6 col-xl">
            <a
                href="{{ route('cases.index', ['stage' => $stage->value]) }}"
                class="text-decoration-none"
            >
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <div class="text-muted small mb-2">
                            {{ $stage->value }}
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="fs-3 fw-bold text-dark">
                                {{ number_format($stageCounts[$stage->value] ?? 0) }}
                            </div>
                            <span class="badge {{ $stage->badgeClass() }}">
                                Cases
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('cases.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label small text-muted">Search</label>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control"
                    placeholder="Case number, person, location..."
                >
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label small text-muted">Case Stage</label>
                <select name="stage" class="form-select">
                    <option value="">All Stages</option>
                    @foreach($stages as $stage)
                        <option
                            value="{{ $stage->value }}"
                            @selected(request('stage') === $stage->value)
                        >
                            {{ $stage->value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option
                            value="{{ $status->value }}"
                            @selected(request('status') === $status->value)
                        >
                            {{ $status->value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label small text-muted">Incident Type</label>
                <select name="incident_type_id" class="form-select">
                    <option value="">All Types</option>
                    @foreach($incidentTypes as $type)
                        <option
                            value="{{ $type->id }}"
                            @selected((string) request('incident_type_id') === (string) $type->id)
                        >
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-1 d-grid">
                <button type="submit" class="btn btn-outline-primary">
                    Filter
                </button>
            </div>

            <div class="col-6 col-lg-1 d-grid">
                <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary">
                    Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Case Tracking</strong>
        <span class="text-muted small">
            {{ number_format($cases->total()) }} record(s)
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Case No.</th>
                    <th>Complainant</th>
                    <th>Respondent</th>
                    <th>Case Stage</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Last Updated</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                    @php
                        $statusValue = $case->status?->value ?? (string) $case->status;
                        $statusClass = match($statusValue) {
                            'Pending' => 'text-bg-warning',
                            'Under Investigation' => 'text-bg-primary',
                            'For Mediation' => 'text-bg-info',
                            'Settled', 'Resolved' => 'text-bg-success',
                            'Referred' => 'text-bg-dark',
                            'Dismissed' => 'text-bg-secondary',
                            default => 'text-bg-secondary',
                        };
                    @endphp

                    <tr>
                        <td>
                            <div class="fw-semibold">
                                {{ $case->reference_number }}
                            </div>
                            <div class="small text-muted">
                                {{ $case->incidentType?->name ?? '—' }}
                            </div>
                        </td>

                        <td>
                            @forelse($case->complainants as $person)
                                <div>
                                    {{ trim("{$person->first_name} {$person->middle_name} {$person->last_name} {$person->suffix}") }}
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>

                        <td>
                            @forelse($case->respondents as $person)
                                <div>
                                    {{ trim("{$person->first_name} {$person->middle_name} {$person->last_name} {$person->suffix}") }}
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>

                        <td>
                            <span class="badge {{ $case->case_stage?->badgeClass() ?? 'text-bg-secondary' }}">
                                {{ $case->case_stage?->value ?? 'New' }}
                            </span>
                        </td>

                        <td>
                            <span class="badge {{ $statusClass }}">
                                {{ $statusValue }}
                            </span>
                        </td>

                        <td>
                            {{ $case->currentAssignment?->assignedOfficer?->name ?? '—' }}
                        </td>

                        <td class="text-nowrap">
                            {{ $case->updated_at?->format('M d, Y h:i A') ?? '—' }}
                        </td>

                        <td class="text-end">
                            <a
                                href="{{ route('blotter.show', $case) }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                View / Manage
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            No cases found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($cases->hasPages())
        <div class="card-footer bg-white">
            {{ $cases->links() }}
        </div>
    @endif
</div>
@endsection
