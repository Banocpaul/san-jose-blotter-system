@extends('layouts.app')

@section('title', 'Business Intelligence')
@section('page-title', 'Business Intelligence')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h3 class="mb-1">Business Intelligence</h3>
        <p class="text-muted mb-0">Case operations, outcomes, and processing delays for Barangay San Jose.</p>
    </div>
    <div class="small text-muted text-md-end">
        <strong>{{ number_format($totalCases) }}</strong> matching cases<br>
        As of {{ $asOf->setTimezone('Asia/Manila')->format('M d, Y · h:i A').' PHT' }}
    </div>
</div>

<nav class="analytics-tabs mb-4" aria-label="Analytics sections">
    @foreach(['operations' => ['Case Operations', 'bi-briefcase'], 'performance' => ['Resolution & Performance', 'bi-check2-circle'], 'bottlenecks' => ['SLA & Bottlenecks', 'bi-hourglass-split']] as $key => [$label, $icon])
        <a href="{{ route('analytics.index', array_merge($filters, ['tab' => $key])) }}"
            class="analytics-tab {{ $activeTab === $key ? 'active' : '' }}" @if($activeTab === $key) aria-current="page" @endif>
            <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
        </a>
    @endforeach
</nav>

<div class="card mb-4">
    <div class="card-header"><strong>Analytics Filters</strong><span class="small text-muted ms-2">Apply to every tab</span></div>
    <div class="card-body">
        <form method="GET" action="{{ route('analytics.index') }}">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="date_from" class="form-label">Incident Date From</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="date_to" class="form-label">Incident Date To</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="case_stage" class="form-label">Current Stage</label>
                    <select id="case_stage" name="case_stage" class="form-select">
                        <option value="">All Stages</option>
                        @foreach($caseStages as $stage)
                            <option value="{{ $stage->value }}" @selected(($filters['case_stage'] ?? '') === $stage->value)>{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="record_status" class="form-label">Record Status</label>
                    <select id="record_status" name="record_status" class="form-select">
                        <option value="">All Record Statuses</option>
                        @foreach($recordStatuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['record_status'] ?? '') === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="incident_type_id" class="form-label">Incident Type</label>
                    <select id="incident_type_id" name="incident_type_id" class="form-select">
                        <option value="">All Incident Types</option>
                        @foreach($incidentTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) ($filters['incident_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}{{ $type->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="sitio" class="form-label">Party Sitio</label>
                    <select id="sitio" name="sitio" class="form-select">
                        <option value="">All Sitios</option>
                        @foreach($sitios as $sitio)
                            <option value="{{ $sitio }}" @selected(($filters['sitio'] ?? '') === $sitio)>{{ $sitio }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="councilor_id" class="form-label">Current Councilor</label>
                    <select id="councilor_id" name="councilor_id" class="form-select">
                        <option value="">All Councilors</option>
                        @foreach($councilors as $councilor)
                            <option value="{{ $councilor->id }}" @selected((string) ($filters['councilor_id'] ?? '') === (string) $councilor->id)>{{ $councilor->name }}{{ $councilor->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="mediation_outcome" class="form-label">Recorded Mediation Outcome</label>
                    <select id="mediation_outcome" name="mediation_outcome" class="form-select">
                        <option value="">All Outcomes</option>
                        @foreach($mediationOutcomes as $outcome)
                            <option value="{{ $outcome }}" @selected(($filters['mediation_outcome'] ?? '') === $outcome)>{{ $outcome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="target_days" class="form-label">Processing Target (days)</label>
                    <input id="target_days" type="number" min="1" max="3650" step="1" name="target_days" value="{{ $targetDays ?? '' }}" class="form-control" placeholder="Optional" aria-describedby="target_help">
                </div>
                <div class="col-lg-6"><p id="target_help" class="small text-muted mb-0">Compare open-case age against a target you choose. Age starts at the report date; the target applies to this analysis.</p></div>
                <div class="col-lg-3 d-flex flex-wrap gap-2 justify-content-lg-end">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Apply Filters</button>
                    <a href="{{ route('analytics.index', ['tab' => $activeTab]) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $cards = match ($activeTab) {
        'performance' => [
            ['Resolved Records', number_format($resolvedCases), 'Records marked Resolved'],
            ['Dismissed Cases', number_format($dismissedCases), 'Current stage: Dismissed'],
            ['CFA / Referred', number_format($referredCases), 'Current stage: Further Action/CFA'],
            ['Resolution Rate', $resolutionRate === null ? '—' : number_format($resolutionRate, 1).'%', 'Resolved records ÷ all matching cases'],
            ['Avg. Resolution Time', $avgResolutionDays === null ? '—' : number_format($avgResolutionDays, 1).' days', $resolutionSamples.' resolved cases with usable dates'],
        ],
        'bottlenecks' => [
            ['Open Cases', number_format($openCases), 'Current unresolved workload'],
            ['Beyond Target', $beyondTargetCases === null ? 'Not set' : number_format($beyondTargetCases), $targetDays === null ? 'Enter a processing target above' : 'Age greater than '.$targetDays.' days'],
            ['Unassigned Open Cases', number_format($unassignedCases), 'No current councilor assignment'],
            ['Report Date Unavailable', number_format($unknownAgeCases), 'Open cases excluded from target checks'],
        ],
        default => [
            ['Total Cases', number_format($totalCases), 'All matching records'],
            ['Open Cases', number_format($openCases), 'Records marked Open'],
            ['Resolved Records', number_format($resolvedCases), 'Records marked Resolved'],
            ['Closed Records', number_format($closedCases), 'Includes dismissals and referrals'],
        ],
    };
@endphp
<div class="row g-3 mb-4">
    @foreach($cards as [$label, $value, $description])
        <div class="col-sm-6 col-xl"><div class="card h-100"><div class="card-body">
            <div class="text-muted small mb-2">{{ $label }}</div>
            <div class="fs-3 fw-semibold">{{ $value }}</div>
            <div class="text-muted small mt-2">{{ $description }}</div>
        </div></div></div>
    @endforeach
</div>

@if($totalCases === 0)
    <div class="alert alert-light border mb-4" role="status">No cases match the selected filters. Adjust the filters or reset them to see available records.</div>
@endif

@if($activeTab === 'operations')
    <div class="row g-4 mb-4">
        <div class="col-xl-8">@include('analytics.partials.chart', ['id' => 'caseTrendChart', 'title' => 'Monthly Incident Trend', 'chartDescription' => 'Cases grouped by incident month, including zero-case months between records.', 'rows' => $caseTrend, 'chartType' => 'line'])</div>
        <div class="col-xl-4">@include('analytics.partials.chart', ['id' => 'statusChart', 'title' => 'Record Status Distribution', 'rows' => $statusDistribution, 'chartType' => 'doughnut'])</div>
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'stageChart', 'title' => 'Cases by Current Stage', 'rows' => $stageDistribution, 'horizontal' => true])</div>
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'incidentChart', 'title' => 'Most Common Incident Types', 'rows' => $incidentDistribution, 'horizontal' => true])</div>
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'sitioChart', 'title' => 'Cases Involving Each Sitio', 'chartDescription' => 'Based on complainant/respondent addresses. A case may involve more than one Sitio; each case is counted once per Sitio.', 'rows' => $sitioDistribution])</div>
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'councilorChart', 'title' => 'Current Councilor Workload', 'chartDescription' => 'Open cases only, counted under their current councilor.', 'rows' => $councilorWorkload, 'horizontal' => true])</div>
    </div>
    @include('analytics.partials.cases', ['title' => 'Recent Matching Cases', 'cases' => $recentCases, 'aging' => false])
@elseif($activeTab === 'performance')
    <div class="row g-4 mb-4">
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'outcomeChart', 'title' => 'Current Case Outcomes', 'chartDescription' => 'Resolved records, dismissed stages, and CFA/referral stages. Referral and dismissal are shown separately from resolution.', 'rows' => $outcomeDistribution])</div>
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'mediationChart', 'title' => 'Recorded Mediation Outcomes', 'chartDescription' => 'Distinct cases for each recorded hearing outcome. A case can appear in multiple categories across hearings.', 'rows' => $mediationDistribution, 'horizontal' => true])</div>
    </div>
    <div class="card mb-4"><div class="card-header"><strong>Resolution Measurement</strong></div><div class="card-body">
        <p class="mb-2">Resolution rate counts records marked <strong>Resolved</strong> out of all matching cases. Dismissals and referrals do not increase this rate.</p>
        <p class="mb-0 text-muted">Average time runs from the report date to the recorded case resolution date. {{ number_format($resolutionSamples) }} usable records are included; {{ number_format($missingResolutionDates) }} resolved records have missing or invalid dates and are excluded. An unavailable average appears as “—”.</p>
    </div></div>
    @include('analytics.partials.cases', ['title' => 'Recent Matching Cases', 'cases' => $recentCases, 'aging' => false])
@else
    <div class="alert alert-light border mb-4">Age is measured in whole elapsed days from the report date, as of {{ $asOf->setTimezone('Asia/Manila')->format('M d, Y') }}. Stage totals show where open cases currently sit; time spent within each stage is unavailable in existing records.</div>
    <div class="row g-4 mb-4">
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'agingChart', 'title' => 'Open-Case Aging', 'rows' => $agingDistribution])</div>
        <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'backlogChart', 'title' => 'Open Cases by Current Stage', 'rows' => $stageBacklog, 'horizontal' => true])</div>
    </div>
    @include('analytics.partials.cases', ['title' => 'Oldest Open Cases', 'cases' => $oldestCases, 'aging' => true])
@endif
@endsection

@push('styles')
<style>
    .analytics-tabs { display: flex; flex-wrap: wrap; gap: .6rem; }
    .analytics-tab { display: inline-flex; align-items: center; gap: .5rem; padding: .8rem 1rem; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--surface); color: var(--ink-muted); text-decoration: none; font-weight: 600; }
    .analytics-tab.active { color: #fff; background: var(--accent); border-color: var(--accent); }
    .analytics-tab:hover { border-color: var(--accent); }
    .analytics-tab:focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
    .analytics-chart { height: 340px; }
    .analytics-empty { min-height: 180px; display: grid; place-items: center; text-align: center; }
    @media (max-width: 575px) { .analytics-tab { width: 100%; } .analytics-chart { height: 300px; } }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        document.querySelectorAll('[data-analytics-chart]').forEach(canvas => {
            const message = document.createElement('p');
            message.className = 'text-muted small';
            message.textContent = 'Chart could not load. Open “View chart values” below to see the data.';
            canvas.parentElement.replaceWith(message);
        });
        return;
    }
    document.querySelectorAll('[data-analytics-chart]').forEach(canvas => {
        const rows = JSON.parse(canvas.dataset.rows);
        const type = canvas.dataset.type;
        const horizontal = canvas.dataset.horizontal === 'true';
        const palette = ['#2563eb', '#16794f', '#667085', '#b26a00', '#5b8cff', '#23395d', '#7aa2ff'];
        new Chart(canvas, {
            type,
            data: {
                labels: rows.map(row => row.label),
                datasets: [{
                    label: 'Cases', data: rows.map(row => Number(row.total)),
                    backgroundColor: type === 'line' ? 'rgba(37, 99, 235, .12)' : (type === 'doughnut' ? palette : '#2563eb'),
                    borderColor: type === 'doughnut' ? '#ffffff' : '#2563eb', borderWidth: type === 'doughnut' ? 3 : 1,
                    borderRadius: type === 'bar' ? 5 : 0, fill: type === 'line', tension: .25, pointRadius: 3,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: { legend: { display: type === 'doughnut', position: 'bottom' } },
                ...(type !== 'doughnut' ? { scales: {
                    [horizontal ? 'x' : 'y']: { beginAtZero: true, ticks: { precision: 0 } },
                } } : {}),
            },
        });
    });
});
</script>
@endpush
