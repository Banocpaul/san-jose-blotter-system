@extends('layouts.app')

@section('title', 'Reports & Export')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Reports & Export</h3>
        <div class="text-muted">
            Generate filtered case reports, print official copies, and export records to Excel.
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a
            href="{{ route('reports.print', request()->except('page')) }}"
            target="_blank"
            class="btn btn-outline-dark"
        >
            <i class="bi bi-printer me-1"></i>
            Print
        </a>

        <a
            href="{{ route('reports.export', request()->except('page')) }}"
            class="btn btn-success"
        >
            <i class="bi bi-file-earmark-excel me-1"></i>
            Export Excel
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body">
                <div class="text-muted small mb-2">Total Cases</div>
                <div class="fs-3 fw-bold">
                    {{ number_format((int) ($summary->total_cases ?? 0)) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body">
                <div class="text-muted small mb-2">Active Cases</div>
                <div class="fs-3 fw-bold text-primary">
                    {{ number_format((int) ($summary->active_cases ?? 0)) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body">
                <div class="text-muted small mb-2">Settled / Resolved</div>
                <div class="fs-3 fw-bold text-success">
                    {{ number_format((int) ($summary->settled_cases ?? 0)) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-body">
                <div class="text-muted small mb-2">Further Action / CFA</div>
                <div class="fs-3 fw-bold text-dark">
                    {{ number_format((int) ($summary->cfa_cases ?? 0)) }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Report Filters</strong>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label">Search</label>
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    value="{{ request('search') }}"
                    placeholder="Case number, person, or location"
                >
            </div>

            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label">Incident Type</label>
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

            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label">Case Stage</label>
                <select name="case_stage" class="form-select">
                    <option value="">All Stages</option>
                    @foreach($caseStages as $stage)
                        <option
                            value="{{ $stage->value }}"
                            @selected(request('case_stage') === $stage->value)
                        >
                            {{ $stage->value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($caseStatuses as $status)
                        <option
                            value="{{ $status->value }}"
                            @selected(request('status') === $status->value)
                        >
                            {{ $status->value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label">Date From</label>
                <input
                    type="date"
                    name="date_from"
                    class="form-control"
                    value="{{ request('date_from') }}"
                >
            </div>

            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label">Date To</label>
                <input
                    type="date"
                    name="date_to"
                    class="form-control"
                    value="{{ request('date_to') }}"
                >
            </div>

            <div class="col-12 col-md-6 col-lg-auto d-grid">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-funnel me-1"></i>
                    Apply Filters
                </button>
            </div>

            <div class="col-12 col-md-6 col-lg-auto d-grid">
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                    Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Case Report</strong>
        <span class="text-muted small">
            {{ number_format($cases->total()) }} record(s)
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Case No.</th>
                    <th>Incident Date</th>
                    <th>Incident Type</th>
                    <th>Complainant</th>
                    <th>Respondent</th>
                    <th>Case Stage</th>
                    <th>Status</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                    @php
                        $statusValue = $case->status?->value ?? (string) $case->status;
                    @endphp
                    <tr>
                        <td class="text-nowrap fw-semibold">
                            {{ $case->reference_number }}
                        </td>

                        <td class="text-nowrap">
                            {{ $case->incident_date?->format('M d, Y') ?? '—' }}
                        </td>

                        <td>
                            {{ $case->incidentType?->name ?? '—' }}
                        </td>

                        <td style="min-width: 220px; white-space: normal;">
                            @forelse($case->complainants as $person)
                                <div class="mb-1">
                                    {{ trim("{$person->first_name} {$person->middle_name} {$person->last_name} {$person->suffix}") }}
                                    <span class="badge text-bg-light border">
                                        {{ $person->is_san_jose_resident ? 'Resident' : 'Non-Resident' }}
                                    </span>
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>

                        <td style="min-width: 220px; white-space: normal;">
                            @forelse($case->respondents as $person)
                                <div class="mb-1">
                                    {{ trim("{$person->first_name} {$person->middle_name} {$person->last_name} {$person->suffix}") }}
                                    <span class="badge text-bg-light border">
                                        {{ $person->is_san_jose_resident ? 'Resident' : 'Non-Resident' }}
                                    </span>
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
                            {{ $statusValue ?: '—' }}
                        </td>

                        <td style="min-width: 220px; white-space: normal;">
                            {{ $case->location ?: '—' }}
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
