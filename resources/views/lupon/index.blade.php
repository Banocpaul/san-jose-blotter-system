@extends('layouts.app')

@section('title', 'Lupon & Mediation')

@section('content')

@php
    $cards = [
        [
            'label' => 'For Mediation',
            'value' => $kpis['for_mediation'],
            'icon' => 'bi-chat-square-text',
            'class' => 'border-info',
        ],
        [
            'label' => 'For Pangkat/Conciliation',
            'value' => $kpis['for_pangkat'],
            'icon' => 'bi-people',
            'class' => 'border-warning',
        ],
        [
            'label' => 'Pending Proceedings',
            'value' => $kpis['pending_proceedings'],
            'icon' => 'bi-hourglass-split',
            'class' => 'border-primary',
        ],
        [
            'label' => 'Settled',
            'value' => $kpis['settled'],
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
        <h3 class="mb-1">Lupon & Mediation</h3>
        <div class="text-muted">
            Manage mediation and Pangkat conciliation proceedings from one case-linked module.
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach($cards as $card)
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100 border-start border-4 {{ $card['class'] }}">
                <div class="card-body d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-muted small mb-1">{{ $card['label'] }}</div>
                        <div class="fs-3 fw-bold">{{ number_format($card['value']) }}</div>
                    </div>
                    <i class="bi {{ $card['icon'] }} fs-2 text-muted"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('lupon.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-lg-6">
                <label class="form-label">Search</label>
                <input
                    type="search"
                    name="search"
                    class="form-control"
                    value="{{ request('search') }}"
                    placeholder="Case number, person, or location"
                >
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">Case Stage</label>
                <select name="stage" class="form-select">
                    <option value="">All active Lupon cases</option>
                    @foreach($activeStages as $stage)
                        <option value="{{ $stage }}" @selected(request('stage') === $stage)>
                            {{ $stage }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">
                    <i class="bi bi-search me-1"></i>
                    Filter
                </button>
                <a href="{{ route('lupon.index') }}" class="btn btn-outline-secondary">
                    Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong>Active Proceedings</strong>
            <div class="small text-muted">
                Cases automatically appear here when their stage becomes For Mediation or For Pangkat/Conciliation.
            </div>
        </div>
        <span class="badge text-bg-light border">
            {{ number_format($cases->total()) }} case(s)
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Case Reference</th>
                    <th>Current Stage</th>
                    <th>Parties Involved</th>
                    <th>Latest Proceeding</th>
                    <th>Assigned Lupon</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                    @php
                        $stage = $case->case_stage instanceof \App\Enums\CaseStage
                            ? $case->case_stage->value
                            : $case->case_stage;

                        $latest = $case->latestMediationSession;

                        $stageClass = $stage === 'For Pangkat/Conciliation'
                            ? 'text-bg-warning'
                            : 'text-bg-info';
                    @endphp

                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $case->reference_number }}</div>
                            <div class="small text-muted">
                                {{ $case->incidentType?->name ?? 'Unclassified' }}
                            </div>
                        </td>

                        <td>
                            <span class="badge {{ $stageClass }}">{{ $stage }}</span>
                        </td>

                        <td style="min-width: 230px;">
                            <div class="small">
                                <strong>Comp:</strong>
                                {{ $case->complainants->map($personName)->filter()->join(', ') ?: '—' }}
                            </div>
                            <div class="small mt-1">
                                <strong>Resp:</strong>
                                {{ $case->respondents->map($personName)->filter()->join(', ') ?: '—' }}
                            </div>
                        </td>

                        <td>
                            @if($latest)
                                <div class="fw-semibold">
                                    {{ $latest->proceeding_type ?: 'Mediation' }}
                                </div>
                                <div class="small text-muted">
                                    Hearing #{{ $latest->hearing_number }}
                                </div>
                            @else
                                <span class="text-muted">Not scheduled</span>
                            @endif
                        </td>

                        <td>
                            {{ $latest?->luponMember?->name ?? 'Not assigned' }}
                        </td>

                        <td>
                            @if($latest?->scheduled_date)
                                <div>{{ $latest->scheduled_date->format('M d, Y') }}</div>
                                @if($latest->scheduled_time)
                                    <div class="small text-muted">
                                        {{ date('h:i A', strtotime($latest->scheduled_time)) }}
                                    </div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <td>
                            @if($latest)
                                <span class="badge text-bg-light border">{{ $latest->status }}</span>
                                @if($latest->outcome)
                                    <div class="small text-muted mt-1">
                                        {{ $latest->outcome->outcome }}
                                    </div>
                                @endif
                            @else
                                <span class="badge text-bg-secondary">Waiting</span>
                            @endif
                        </td>

                        <td class="text-end">
                            <a href="{{ route('blotter.show', $case) }}" class="btn btn-sm btn-outline-primary">
                                Open Case
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            No active Lupon or mediation cases found.
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
