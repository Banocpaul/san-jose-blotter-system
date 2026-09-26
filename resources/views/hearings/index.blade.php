@extends('layouts.app')

@section('title', 'Hearing Schedules')

@section('content')
@php
    $cards = [
        [
            'label' => 'Upcoming',
            'value' => $kpis['upcoming'],
            'view' => 'upcoming',
            'icon' => 'bi-calendar2-week',
            'class' => 'border-primary',
        ],
        [
            'label' => "Today's Schedule",
            'value' => $kpis['today'],
            'view' => 'today',
            'icon' => 'bi-calendar-check',
            'class' => 'border-warning',
        ],
        [
            'label' => 'Completed',
            'value' => $kpis['completed'],
            'view' => 'completed',
            'icon' => 'bi-check2-circle',
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
        <h3 class="mb-1">Hearing Schedules</h3>
        <div class="text-muted">
            Central schedule for mediation and Pangkat conciliation hearings.
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach($cards as $card)
        <div class="col-12 col-md-4">
            <a
                href="{{ route('hearings.index', ['view' => $card['view']]) }}"
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
        <form method="GET" action="{{ route('hearings.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-lg-7">
                <label class="form-label">Search</label>
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    value="{{ request('search') }}"
                    placeholder="Case number, complainant, respondent, or assigned Lupon"
                >
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">Schedule View</label>
                <select name="view" class="form-select">
                    <option value="">All Schedules</option>
                    <option value="upcoming" @selected(request('view') === 'upcoming')>Upcoming</option>
                    <option value="today" @selected(request('view') === 'today')>Today's Schedule</option>
                    <option value="completed" @selected(request('view') === 'completed')>Completed</option>
                </select>
            </div>

            <div class="col-6 col-lg-1 d-grid">
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>

            <div class="col-6 col-lg-1 d-grid">
                <a href="{{ route('hearings.index') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Hearing Schedule List</strong>
        <span class="text-muted small">
            {{ number_format($sessions->total()) }} schedule(s)
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Case Reference</th>
                    <th>Hearing Stage & Schedule</th>
                    <th>Parties Involved</th>
                    <th>Assigned Officer / Pangkat</th>
                    <th>Status</th>
                    <th class="text-end">Quick Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                    @php
                        $case = $session->blotterCase;
                        $isToday = $session->scheduled_date?->isToday();
                        $isUpcoming = $session->status === 'Scheduled'
                            && $session->scheduled_date?->isFuture();
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
                            <div class="mb-1">
                                <span class="badge text-bg-light border">
                                    {{ $session->proceeding_type ?: 'Mediation' }}
                                </span>
                                <span class="small text-muted">
                                    Hearing #{{ $session->hearing_number }}
                                </span>
                            </div>
                            <div class="fw-semibold">
                                {{ $session->scheduled_date?->format('M d, Y') ?? '—' }}
                                @if($session->scheduled_time)
                                    - {{ date('h:i A', strtotime($session->scheduled_time)) }}
                                @endif
                            </div>
                            <div class="small text-muted">
                                {{ $session->venue ?: 'Barangay Hall' }}
                            </div>
                        </td>

                        <td style="min-width: 240px;">
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
                            <div class="fw-semibold">
                                {{ $session->luponMember?->name ?? 'Not assigned' }}
                            </div>
                            <div class="small text-muted">
                                {{ $session->proceeding_type === 'Pangkat Conciliation' ? 'Pangkat / Lupon' : 'Lupon / Mediator' }}
                            </div>
                        </td>

                        <td>
                            @if($session->status === 'Completed')
                                <span class="badge text-bg-success">Completed</span>
                            @elseif($isToday)
                                <span class="badge text-bg-warning">Today</span>
                            @elseif($isUpcoming)
                                <span class="badge text-bg-primary">Upcoming</span>
                            @else
                                <span class="badge text-bg-secondary">{{ $session->status }}</span>
                            @endif

                            @if($session->outcome)
                                <div class="small text-muted mt-1">
                                    {{ $session->outcome->outcome }}
                                </div>
                            @endif
                        </td>

                        <td class="text-end">
                            @if($case)
                                <a
                                    href="{{ route('blotter.show', $case) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    View Case
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            No hearing schedules found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sessions->hasPages())
        <div class="card-footer bg-white">
            {{ $sessions->links() }}
        </div>
    @endif
</div>
@endsection
