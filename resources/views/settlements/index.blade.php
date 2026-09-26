@extends('layouts.app')

@section('title', 'Settlement & Resolutions')

@section('content')
@php
    $cards = [
        [
            'label' => 'Pending',
            'value' => $kpis['pending'],
            'status' => 'Pending',
            'icon' => 'bi-hourglass-split',
            'class' => 'border-warning',
        ],
        [
            'label' => 'Finalized',
            'value' => $kpis['finalized'],
            'status' => 'Finalized',
            'icon' => 'bi-file-earmark-check',
            'class' => 'border-primary',
        ],
        [
            'label' => 'Resolved',
            'value' => $kpis['resolved'],
            'status' => 'Resolved',
            'icon' => 'bi-check-circle',
            'class' => 'border-success',
        ],
    ];

    $personName = function ($person) {
        return trim(collect([
            $person->first_name,
            $person->middle_name,
            $person->last_name,
            $person->suffix,
        ])->filter()->implode(' '));
    };
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Settlement & Resolutions</h3>
        <div class="text-muted">
            Finalize settlement records and complete resolved cases without duplicating case information.
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="row g-3 mb-4">
    @foreach($cards as $card)
        <div class="col-12 col-md-4">
            <a
                href="{{ route('settlements.index', ['status' => $card['status']]) }}"
                class="text-decoration-none"
            >
                <div class="card shadow-sm h-100 border-start border-4 {{ $card['class'] }}">
                    <div class="card-body d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <div class="text-muted small mb-1">{{ $card['label'] }}</div>
                            <div class="fs-3 fw-bold text-dark">
                                {{ number_format($card['value']) }}
                            </div>
                        </div>
                        <i class="bi {{ $card['icon'] }} fs-2 text-muted"></i>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('settlements.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-lg-7">
                <label class="form-label">Search</label>
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    value="{{ request('search') }}"
                    placeholder="Case number, person, or resolution type"
                >
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">Resolution Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach(['Pending', 'Finalized', 'Resolved'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-1 d-grid">
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>

            <div class="col-6 col-lg-1 d-grid">
                <a href="{{ route('settlements.index') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Settlement & Resolution Records</strong>
        <span class="text-muted small">
            {{ number_format($resolutions->total()) }} record(s)
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Case Reference</th>
                    <th>Parties</th>
                    <th>Resolution Type</th>
                    <th>Agreement / Resolution</th>
                    <th>Status</th>
                    <th>Processed</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resolutions as $resolution)
                    @php
                        $case = $resolution->blotterCase;
                        $statusClass = match($resolution->status) {
                            'Pending' => 'text-bg-warning',
                            'Finalized' => 'text-bg-primary',
                            'Resolved' => 'text-bg-success',
                            default => 'text-bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">
                                {{ $case?->reference_number ?? '—' }}
                            </div>
                            <div class="small text-muted">
                                {{ $case?->incidentType?->name ?? 'Unclassified' }}
                            </div>
                        </td>

                        <td style="min-width: 230px;">
                            <div class="small">
                                <strong>Comp:</strong>
                                {{ $case?->complainants?->map($personName)->filter()->join(', ') ?: '—' }}
                            </div>
                            <div class="small mt-1">
                                <strong>Resp:</strong>
                                {{ $case?->respondents?->map($personName)->filter()->join(', ') ?: '—' }}
                            </div>
                        </td>

                        <td>
                            <div class="fw-semibold">{{ $resolution->resolution_type }}</div>
                            @if($resolution->mediationOutcome?->mediationSession)
                                <div class="small text-muted">
                                    {{ $resolution->mediationOutcome->mediationSession->proceeding_type ?: 'Mediation' }}
                                    #{{ $resolution->mediationOutcome->mediationSession->hearing_number }}
                                </div>
                            @endif
                        </td>

                        <td style="min-width: 260px; white-space: normal;">
                            @if($resolution->agreement_details)
                                {{ \Illuminate\Support\Str::limit($resolution->agreement_details, 140) }}
                            @else
                                <span class="text-muted">No agreement details recorded.</span>
                            @endif
                        </td>

                        <td>
                            <span class="badge {{ $statusClass }}">
                                {{ $resolution->status }}
                            </span>
                        </td>

                        <td class="small">
                            @if($resolution->status === 'Resolved')
                                <div>{{ $resolution->resolved_at?->format('M d, Y h:i A') ?? '—' }}</div>
                                <div class="text-muted">{{ $resolution->resolvedBy?->name ?? '—' }}</div>
                            @elseif($resolution->status === 'Finalized')
                                <div>{{ $resolution->finalized_at?->format('M d, Y h:i A') ?? '—' }}</div>
                                <div class="text-muted">{{ $resolution->finalizedBy?->name ?? '—' }}</div>
                            @else
                                <span class="text-muted">Awaiting finalization</span>
                            @endif
                        </td>

                        <td class="text-end" style="min-width: 205px;">
                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                @if($case)
                                    <a
                                        href="{{ route('blotter.show', $case) }}"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        View Case
                                    </a>
                                @endif

                                @if(in_array($roleSlug, ['barangay_captain', 'secretary'], true))
                                    @if($resolution->status === 'Pending')
                                        <form method="POST" action="{{ route('settlements.finalize', $resolution) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-primary"
                                                onclick="return confirm('Finalize this settlement record?')"
                                            >
                                                Finalize
                                            </button>
                                        </form>
                                    @elseif($resolution->status === 'Finalized')
                                        <form method="POST" action="{{ route('settlements.resolve', $resolution) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-success"
                                                onclick="return confirm('Mark this case as resolved?')"
                                            >
                                                Mark Resolved
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            No settlement or resolution records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($resolutions->hasPages())
        <div class="card-footer bg-white">
            {{ $resolutions->links() }}
        </div>
    @endif
</div>
@endsection
