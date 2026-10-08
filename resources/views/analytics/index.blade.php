@extends('layouts.app')

@section('title', 'Case Analytics & Insights')
@section('page-title', 'Case Analytics & Insights')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h3 class="mb-1">Case Analytics &amp; Insights</h3>
        <p class="text-muted mb-0">Case operations, outcomes, and processing delays for Barangay San Jose.</p>
    </div>
    <div class="small text-muted text-md-end">
        <strong>{{ number_format($totalCases) }}</strong> matching cases<br>
        As of {{ $asOf->setTimezone('Asia/Manila')->format('M d, Y · h:i A').' PHT' }}
    </div>
</div>

<nav class="analytics-tabs mb-4" aria-label="Analytics sections">
    @foreach(['operations' => ['Case Operations', 'bi-briefcase'], 'performance' => ['Resolution & Performance', 'bi-check2-circle'], 'bottlenecks' => ['SLA & Bottlenecks', 'bi-hourglass-split']] as $key => [$label, $icon])
        <a href="{{ route('analytics.index', array_merge($filters, ['tab' => $key, 'year' => $reportingYear])) }}"
            class="analytics-tab {{ $activeTab === $key ? 'active' : '' }}" @if($activeTab === $key) aria-current="page" @endif>
            <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
        </a>
    @endforeach
</nav>

@php
    $activeFilterCount = count(array_filter(
        array_diff_key($filters, ['tab' => true, 'year' => true]),
        fn ($value) => $value !== null && $value !== ''
    ));
@endphp
<details class="card analytics-filters mb-4">
    <summary class="card-header analytics-filters-toggle">
        <span class="d-flex flex-wrap align-items-center gap-2">
            <strong>Analytics Filters</strong>
            <span class="small text-muted">Apply to every tab</span>
            @if($activeFilterCount > 0)
                <span class="badge text-bg-primary">{{ $activeFilterCount }} active</span>
            @endif
        </span>
        <span class="btn btn-outline-primary btn-sm analytics-filters-action">
            <span class="analytics-filters-show">Show Filters</span>
            <span class="analytics-filters-hide">Hide Filters</span>
            <i class="bi bi-chevron-down" aria-hidden="true"></i>
        </span>
    </summary>
    <div class="card-body">
        <form method="GET" action="{{ route('analytics.index') }}">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <input type="hidden" name="year" value="{{ $reportingYear }}">
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
                    <a href="{{ route('analytics.index', ['tab' => $activeTab, 'year' => $reportingYear]) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</details>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="mb-1">{{ match ($activeTab) { 'performance' => 'Resolution & Performance', 'bottlenecks' => 'SLA & Bottlenecks', default => 'Case Operations' } }}</h5>
        <p class="small text-muted mb-0">Reporting year applies to filing and completion metrics. Open-case and SLA panels show the current matching workload.</p>
    </div>
    <form method="GET" action="{{ route('analytics.index') }}" class="d-flex align-items-center gap-2 analytics-year-form">
        @foreach(array_diff_key($filters, ['year' => true, 'tab' => true]) as $key => $value)
            @if($value !== null && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <label for="reporting_year" class="small fw-semibold text-nowrap">Reporting Year</label>
        <select id="reporting_year" name="year" class="form-select form-select-sm">
            @foreach($availableYears as $year)<option value="{{ $year }}" @selected((string) $reportingYear === (string) $year)>{{ $year }}</option>@endforeach
            <option value="all" @selected($reportingYear === 'all')>All Years</option>
        </select>
        <button class="btn btn-outline-primary btn-sm" type="submit">Apply</button>
    </form>
</div>
@if($totalCases === 0)
    <div class="alert alert-light border mb-4" role="status">No cases match the selected filters. Adjust the filters or reset them to see available records.</div>
@endif
@include('analytics.tabs.'.$activeTab)
@endsection

@push('styles')
<style>
    .analytics-tabs { display: flex; flex-wrap: wrap; gap: .6rem; }
    .analytics-tab { display: inline-flex; align-items: center; gap: .5rem; padding: .8rem 1rem; border: 1px solid var(--line); border-radius: var(--radius-sm); background: var(--surface); color: var(--ink-muted); text-decoration: none; font-weight: 600; }
    .analytics-tab.active { color: #fff; background: var(--accent); border-color: var(--accent); }
    .analytics-tab:hover { border-color: var(--accent); }
    .analytics-tab:focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
    .analytics-filters-toggle { display: flex; align-items: center; justify-content: space-between; gap: 1rem; cursor: pointer; list-style: none; }
    .analytics-filters-toggle::-webkit-details-marker { display: none; }
    .analytics-filters-toggle:focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; border-radius: var(--radius-sm); }
    .analytics-filters:not([open]) > .analytics-filters-toggle { border-bottom: 0; }
    .analytics-filters-action { display: inline-flex; align-items: center; gap: .5rem; flex-shrink: 0; }
    .analytics-filters-hide, .analytics-filters[open] .analytics-filters-show { display: none; }
    .analytics-filters[open] .analytics-filters-hide { display: inline; }
    .analytics-filters[open] .analytics-filters-action i { transform: rotate(180deg); }
    .analytics-metric .card-body { padding: 1.1rem; }
    .analytics-metric-icon { display: inline-flex; padding: .45rem .6rem; background: #eaf2ff; color: #2563eb; border-radius: .6rem; margin-bottom: .75rem; }
    .analytics-metric-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; color: var(--ink-muted); min-height: 2.2em; }
    .analytics-metric-value { font-size: clamp(1.25rem, 1.7vw, 1.9rem); line-height: 1.25; font-weight: 750; margin: .3rem 0 .5rem; overflow-wrap: anywhere; }
    .analytics-metric-description { color: var(--ink-muted); font-size: .72rem; }
    .analytics-metric.metric-dark { background: #334155; color: #fff; }
    .analytics-metric.metric-blue { background: #2563eb; color: #fff; }
    .analytics-metric.metric-dark .analytics-metric-label, .analytics-metric.metric-dark .analytics-metric-description,
    .analytics-metric.metric-blue .analytics-metric-label, .analytics-metric.metric-blue .analytics-metric-description { color: #e0e9ff; }
    .analytics-metric.metric-dark .analytics-metric-icon, .analytics-metric.metric-blue .analytics-metric-icon { color: #fff; background: rgba(255,255,255,.12); }
    .analytics-metric.metric-warning { background: #fffbeb; }
    .analytics-metric.metric-danger, .analytics-delay-cell.delay-danger { background: #fff1f2; }
    .analytics-dashboard-chart { height: 260px; }
    .analytics-date { display: flex; flex-direction: column; align-items: center; width: 45px; flex-shrink: 0; padding: .3rem; border-radius: .6rem; color: #2563eb; background: #eff6ff; line-height: 1.2; }
    .analytics-date span { font-size: .65rem; text-transform: uppercase; }
    .analytics-date strong { font-size: 1.2rem; }
    .analytics-highlight { display: flex; align-items: flex-start; gap: .8rem; padding: .8rem 0; }
    .analytics-highlight + .analytics-highlight { border-top: 1px solid var(--line); }
    .analytics-highlight > i { font-size: 1.3rem; }
    .analytics-delay-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: .6rem; }
    .analytics-delay-cell { padding: .8rem; border-radius: .6rem; background: #f5f8ff; }
    .analytics-delay-cell.delay-warning { background: #fffbeb; }
    .analytics-year-form select { min-width: 105px; }
    .analytics-chart { height: 340px; }
    .analytics-empty { min-height: 180px; display: grid; place-items: center; text-align: center; }
    @media (max-width: 575px) { .analytics-tab { width: 100%; } .analytics-chart { height: 300px; } .analytics-filters-toggle { flex-wrap: wrap; } }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvases = document.querySelectorAll('[data-dashboard-chart]');
    if (typeof Chart === 'undefined') {
        canvases.forEach(canvas => {
            const message = document.createElement('p');
            message.className = 'text-muted small';
            message.textContent = 'Chart could not load. Open “View chart values” below to see the data.';
            canvas.parentElement.replaceWith(message);
        });
        return;
    }
    canvases.forEach(canvas => {
        const rows = JSON.parse(canvas.dataset.rows);
        const series = JSON.parse(canvas.dataset.series);
        const colors = JSON.parse(canvas.dataset.colors);
        const type = canvas.dataset.type;
        const horizontal = canvas.dataset.horizontal === 'true';
        const stacked = canvas.dataset.stacked === 'true';
        const byRow = canvas.dataset.byRow === 'true';
        const days = canvas.dataset.unit === 'days';
        const datasets = Object.entries(series).map(([key, label], index) => ({
            label, data: rows.map(row => row[key] === null ? null : Number(row[key])),
            backgroundColor: type === 'doughnut' || (byRow && Object.keys(series).length === 1) ? rows.map((_, i) => colors[i % colors.length]) : colors[index % colors.length],
            borderColor: type === 'doughnut' ? '#fff' : colors[index % colors.length],
            borderWidth: type === 'doughnut' ? 3 : 2,
            borderRadius: type === 'bar' ? 4 : 0,
            tension: .25, pointRadius: 3, fill: false,
        }));
        new Chart(canvas, {
            type, data: { labels: rows.map(row => row.label), datasets },
            plugins: type === 'doughnut' ? [{
                id: 'dashboardTotal', afterDraw(chart) {
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return;
                    const x = (chartArea.left + chartArea.right) / 2;
                    const y = (chartArea.top + chartArea.bottom) / 2;
                    const total = datasets[0].data.reduce((sum, value) => sum + (Number(value) || 0), 0);
                    ctx.save(); ctx.textAlign = 'center'; ctx.fillStyle = '#334155';
                    ctx.font = '700 22px sans-serif'; ctx.fillText(total.toLocaleString(), x, y);
                    ctx.font = '12px sans-serif'; ctx.fillStyle = '#667085'; ctx.fillText('Cases', x, y + 20); ctx.restore();
                },
            }] : [],
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: type === 'doughnut' || datasets.length > 1, position: 'bottom' },
                    tooltip: { callbacks: { afterLabel: context => days && rows[context.dataIndex].samples !== undefined ? `${rows[context.dataIndex].samples} resolved cases with valid dates` : '' } },
                },
                ...(type !== 'doughnut' ? { scales: {
                    x: { stacked, ...(horizontal ? { beginAtZero: true, ticks: { precision: days ? 1 : 0 } } : {}) },
                    y: { stacked, ...(!horizontal ? { beginAtZero: true, ticks: { precision: days ? 1 : 0 } } : {}) },
                } } : {}),
            },
        });
    });
});
</script>
@endpush
