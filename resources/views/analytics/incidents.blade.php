@extends('layouts.app')
@section('title', 'Incident Analytics')
@section('page-title', 'Incident Analytics')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h3 class="mb-1">Incident Analytics <span class="text-muted">{{ $selectedYear ?? '· All Years' }}</span></h3>
        <p class="text-muted mb-0">What patterns and trends should guide our barangay action?</p>
    </div>
    @if($previousStart && $previousEnd)
        <div class="small text-muted">Compared with {{ $previousStart->format('M d, Y') }}–{{ $previousEnd->format('M d, Y') }}</div>
    @endif
</div>

<div class="card mb-4"><div class="card-body">
    <form id="incident-filters" method="GET" action="{{ route('incident-analytics.index') }}" class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-3">
            <label for="incident_from" class="form-label">Incident Date From</label>
            <input id="incident_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="incident_to" class="form-label">Incident Date To</label>
            <input id="incident_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="incident_type" class="form-label">Incident Type</label>
            <select id="incident_type" name="incident_type_id" class="form-select">
                <option value="">All Incident Types</option>
                @foreach($incidentTypes as $incidentType)
                    <option value="{{ $incidentType->id }}" @selected((string) ($filters['incident_type_id'] ?? '') === (string) $incidentType->id)>{{ $incidentType->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="incident_status" class="form-label">Record Status</label>
            <select id="incident_status" name="record_status" class="form-select">
                <option value="">All Statuses</option>
                @foreach($recordStatuses as $recordStatus)
                    <option value="{{ $recordStatus->value }}" @selected(($filters['record_status'] ?? '') === $recordStatus->value)>{{ $recordStatus->value }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 d-flex flex-wrap align-items-center gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Apply Filters</button>
            <a href="{{ route('incident-analytics.index') }}" class="btn btn-outline-secondary">Reset</a>
            <span class="text-muted small">The year selector below applies to all metrics and charts. Date filters further narrow that year.</span>
        </div>
    </form>
</div></div>

@php
    $metricCards = [
        ['totalIncidents', 'Total Incidents', number_format($totalIncidents), 'bi-exclamation-triangle-fill', 'red', false],
        ['resolutionRate', 'Resolution Rate', $resolutionRate === null ? '—' : number_format($resolutionRate, 1).'%', 'bi-check-circle-fill', 'green', false],
        ['avgResolutionDays', 'Average Resolution Time', $avgResolutionDays === null ? '—' : number_format($avgResolutionDays, 1).' days', 'bi-clock-fill', 'blue', true],
        ['repeatIncidentRate', 'Repeat Incident Rate', $repeatIncidentRate === null ? '—' : number_format($repeatIncidentRate, 1).'%', 'bi-people-fill', 'amber', true],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach($metricCards as [$key, $label, $value, $icon, $color, $lowerIsBetter])
        @php
            $delta = $comparisons[$key];
            $isRate = in_array($key, ['resolutionRate', 'repeatIncidentRate'], true);
            $changeClass = $delta === null || $delta == 0 || $key === 'totalIncidents' ? 'text-muted' : (($lowerIsBetter ? $delta < 0 : $delta > 0) ? 'text-success' : 'text-danger');
        @endphp
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <span class="incident-icon incident-icon-{{ $color }}"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                <div><div class="fs-3 fw-semibold">{{ $value }}</div><div class="fw-semibold small">{{ $label }}</div></div>
            </div>
            <div class="small mt-3 {{ $changeClass }}">
                @if($delta === null)
                    {{ $previousMetrics === null ? 'Select a year or date range to compare' : 'No comparable prior value' }}
                @elseif($delta == 0)
                    No change from previous period
                @else
                    <i class="bi {{ $delta > 0 ? 'bi-arrow-up' : 'bi-arrow-down' }}" aria-hidden="true"></i>
                    {{ number_format(abs($delta), 1) }}{{ $isRate ? ' pp' : '%' }} {{ $delta > 0 ? 'higher' : 'lower' }} than previous period
                @endif
            </div>
            @if($key === 'avgResolutionDays')
                <div class="text-muted small mt-1">{{ number_format($resolutionSamples) }} resolved cases with usable dates</div>
            @elseif($key === 'repeatIncidentRate')
                <div class="text-muted small mt-1">{{ number_format($repeatIncidents) }} repeats among {{ number_format($linkedIncidents) }} linked cases</div>
            @endif
        </div></div></div>
    @endforeach
</div>
@if($totalIncidents === 0)
    <div class="alert alert-light border" role="status">No incidents match the selected year and filters.</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-xl-6">@include('analytics.partials.chart', ['id' => 'incidentTypeChart', 'title' => 'Incidents by Type', 'rows' => $incidentTypeDistribution])</div>
    <div class="col-xl-6"><div class="card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong>Incident Trend Over Time</strong>
            <div class="d-flex align-items-center gap-2">
                <label for="trend_year" class="small mb-0">Year</label>
                <select id="trend_year" name="year" form="incident-filters" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                    <option value="" @selected($selectedYear === null)>All Years</option>
                    @foreach($availableYears as $availableYear)
                        <option value="{{ $availableYear }}" @selected($selectedYear === $availableYear)>{{ $availableYear }}</option>
                    @endforeach
                </select>
                <button type="submit" form="incident-filters" class="btn btn-sm btn-outline-primary">Show</button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">Monthly counts {{ $selectedYear ? 'for '.$selectedYear.' · January to December' : 'across recorded years' }}. Current-year figures include incidents through today.</p>
            <div class="incident-chart"><canvas id="monthlyTrendChart" role="img" aria-label="Monthly incident trend" data-analytics-chart data-type="line" data-rows="{{ $monthlyTrend->toJson() }}"></canvas></div>
            <details class="mt-3"><summary class="small text-muted">View monthly counts</summary>
                <div class="table-responsive"><table class="table table-sm mt-2"><thead><tr><th>Month</th><th class="text-end">Incidents</th></tr></thead><tbody>
                @forelse($monthlyTrend as $month)
                    <tr><td>{{ $month['label'] }}</td><td class="text-end">{{ number_format($month['total']) }}</td></tr>
                @empty
                    <tr><td colspan="2">No matching data.</td></tr>
                @endforelse
                </tbody></table></div>
            </details>
        </div>
    </div></div>
    <div class="col-xl-4">@include('analytics.partials.chart', ['id' => 'sitioChart', 'title' => 'Incidents Involving Sitio', 'chartDescription' => 'Complainant/respondent addresses; each case counts once per Sitio and may involve several Sitios.', 'rows' => $sitioDistribution, 'horizontal' => true])</div>
    <div class="col-xl-4">@include('analytics.partials.chart', ['id' => 'dayOfWeekChart', 'title' => 'Incidents by Day of the Week', 'rows' => $dayOfWeek])</div>
    <div class="col-xl-4">@include('analytics.partials.chart', ['id' => 'outcomeChart', 'title' => 'Resolution Outcome', 'chartDescription' => 'Each incident appears once, based on its current record status and workflow stage.', 'rows' => $outcomeDistribution, 'chartType' => 'doughnut'])</div>
</div>

<details class="card mb-4"><summary class="card-header"><strong>How the metrics are calculated</strong></summary><div class="card-body">
    <p><strong>Total Incidents:</strong> count of matching, non-deleted cases by incident date.</p>
    <p><strong>Resolution Rate:</strong> resolved records ÷ total incidents × 100. Dismissals and referrals are separate outcomes.</p>
    <p><strong>Average Resolution Time:</strong> total elapsed days from report to resolution ÷ resolved cases with usable dates. Missing, negative, and future resolution dates are excluded.</p>
    <p><strong>Repeat Incident Rate:</strong> cases involving a registered complainant or respondent from an earlier incident ÷ matching cases with registered participants × 100. Earlier incidents are checked across all years; each case counts once. {{ number_format($totalIncidents - $linkedIncidents) }} cases without registered participants are excluded because a repeat cannot be verified.</p>
    <p class="mb-0 text-muted">Rate changes are percentage points (pp). Count and duration changes are percentages. A selected year compares the same calendar dates in the preceding year; a custom date range compares the preceding range of equal length. An unavailable value appears as “—”.</p>
</div></details>

<div class="card mb-4"><div class="card-header"><strong>Resolution Time by Incident Type</strong></div>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Incident Type</th><th>Usable Resolved Cases</th><th>Average Time</th></tr></thead><tbody>
        @forelse($resolutionByType as $row)
            <tr><td>{{ $row->label }}</td><td>{{ number_format($row->samples) }}</td><td>{{ number_format($row->avg_days, 1) }} days</td></tr>
        @empty
            <tr><td colspan="3" class="text-muted text-center py-4">No resolved cases with usable dates match the filters.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@include('analytics.partials.cases', ['title' => 'Recent Incidents', 'cases' => $recentIncidents, 'aging' => false])
@endsection

@push('styles')
<style>
    .incident-icon { flex-shrink: 0; display: grid; place-items: center; width: 48px; height: 48px; border-radius: 12px; font-size: 1.5rem; }
    .incident-icon-red { color: #c23b45; background: #fde6e7; }
    .incident-icon-green { color: #16794f; background: #ddf4e7; }
    .incident-icon-blue { color: #2563eb; background: #e8f0ff; }
    .incident-icon-amber { color: #b26a00; background: #fff2cb; }
    .incident-chart, .analytics-chart { height: 300px; }
    .analytics-empty { min-height: 180px; display: grid; place-items: center; text-align: center; }
    #trend_year { width: auto; min-width: 100px; }
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvases = document.querySelectorAll('[data-analytics-chart]');
    if (typeof Chart === 'undefined') {
        canvases.forEach(canvas => {
            const message = document.createElement('p');
            message.className = 'text-muted small';
            message.textContent = 'Chart could not load. Open the chart values below to see the data.';
            canvas.parentElement.replaceWith(message);
        });
        return;
    }
    const palette = ['#2563eb', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#eab308', '#64748b'];
    const outcomeColors = ['#22c55e', '#2563eb', '#eab308', '#64748b', '#ef4444'];
    canvases.forEach(canvas => {
        const rows = JSON.parse(canvas.dataset.rows);
        const type = canvas.dataset.type;
        const horizontal = canvas.dataset.horizontal === 'true';
        const centerTotal = {
            id: 'incidentTotal', afterDraw(chart) {
                if (canvas.id !== 'outcomeChart') return;
                const { ctx, chartArea: { left, right, top, bottom } } = chart;
                ctx.save(); ctx.textAlign = 'center'; ctx.fillStyle = '#171a21'; ctx.font = 'bold 26px sans-serif';
                ctx.fillText(rows.reduce((total, row) => total + Number(row.total), 0), (left + right) / 2, (top + bottom) / 2);
                ctx.font = '12px sans-serif'; ctx.fillText('Incidents', (left + right) / 2, (top + bottom) / 2 + 22); ctx.restore();
            },
        };
        new Chart(canvas, {
            type, plugins: [centerTotal],
            data: { labels: rows.map(row => row.label), datasets: [{
                label: 'Incidents', data: rows.map(row => Number(row.total)),
                backgroundColor: type === 'line' ? 'rgba(37, 99, 235, .12)' : (canvas.id === 'outcomeChart' ? outcomeColors : palette),
                borderColor: type === 'line' ? '#2563eb' : '#fff', borderWidth: type === 'line' ? 2 : 1,
                borderRadius: type === 'bar' ? 5 : 0, fill: type === 'line', tension: .25, pointRadius: 4,
            }] },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x',
                cutout: '68%', plugins: { legend: { display: type === 'doughnut', position: 'bottom' } },
                ...(type !== 'doughnut' ? { scales: { [horizontal ? 'x' : 'y']: { beginAtZero: true, ticks: { precision: 0 } } } } : {}),
            },
        });
    });
});
</script>
@endpush
