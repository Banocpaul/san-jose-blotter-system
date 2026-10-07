@extends('layouts.app')

@section('title', 'Incident Analytics')
@section('page-title', 'Incident Analytics')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Incident Analytics</h3>
        <div class="text-muted">
            Analyze incident volume, patterns, locations, timing, status, and resolution performance.
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('incident-analytics.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input
                    type="date"
                    name="date_from"
                    value="{{ request('date_from') }}"
                    class="form-control"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">To</label>
                <input
                    type="date"
                    name="date_to"
                    value="{{ request('date_to') }}"
                    class="form-control"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">Incident Type</label>
                <select name="incident_type_id" class="form-select">
                    <option value="">All Incident Types</option>
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

            <div class="col-md-3">
                <label class="form-label">Record Status</label>
                <select name="record_status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($recordStatuses as $status)
                        <option
                            value="{{ $status->value }}"
                            @selected(request('record_status') === $status->value)
                        >
                            {{ $status->value }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>
                    Apply Filters
                </button>

                <a
                    href="{{ route('incident-analytics.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Total Incidents', $totalIncidents, 'bi-journal-text'],
        ['Open Incidents', $openIncidents, 'bi-folder2-open'],
        ['Resolved Incidents', $resolvedIncidents, 'bi-check2-circle'],
        ['Resolution Rate', number_format($resolutionRate, 1) . '%', 'bi-percent'],
        ['Avg. Resolution', number_format($avgResolutionDays, 1) . ' days', 'bi-clock-history'],
        ['Closed Records', $closedIncidents, 'bi-archive'],
    ] as [$label, $value, $icon])
        <div class="col-sm-6 col-xl-2">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted small mb-2">{{ $label }}</div>
                            <div class="fs-4 fw-semibold">{{ $value }}</div>
                        </div>

                        <div class="text-primary fs-5">
                            <i class="bi {{ $icon }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">Most Common Incident</div>
                <div class="fw-semibold">
                    {{ $topIncidentType?->name ?? 'No data' }}
                </div>
                <div class="small text-muted mt-1">
                    {{ number_format((int) ($topIncidentType?->total ?? 0)) }} case(s)
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">Busiest Day</div>
                <div class="fw-semibold">
                    {{ $busiestDay?->day_name ?? 'No data' }}
                </div>
                <div class="small text-muted mt-1">
                    {{ number_format((int) ($busiestDay?->total ?? 0)) }} incident(s)
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">Peak Time Window</div>
                <div class="fw-semibold">
                    {{ $peakTimeBucket?->time_bucket ?? 'No data' }}
                </div>
                <div class="small text-muted mt-1">
                    {{ number_format((int) ($peakTimeBucket?->total ?? 0)) }} incident(s)
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">Top Incident Location</div>
                <div class="fw-semibold">
                    {{ $topLocation?->location ?? 'No data' }}
                </div>
                <div class="small text-muted mt-1">
                    {{ number_format((int) ($topLocation?->total ?? 0)) }} incident(s)
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>Incidents Over Time</strong>
                <div class="small text-muted">
                    Monthly incident volume based on incident date.
                </div>
            </div>
            <div class="card-body">
                <div style="height: 320px;">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>Incident Type Distribution</strong>
                <div class="small text-muted">
                    Most frequently recorded incident categories.
                </div>
            </div>
            <div class="card-body">
                <div style="height: 320px;">
                    <canvas id="incidentTypeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>Incidents by Day of Week</strong>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="dayOfWeekChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>Incidents by Time of Day</strong>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="timeOfDayChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>Top Incident Locations</strong>
                <div class="small text-muted">
                    Highest-frequency locations from blotter records.
                </div>
            </div>
            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="locationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>Status by Incident Type</strong>
                <div class="small text-muted">
                    Open, resolved, and closed records for each incident category.
                </div>
            </div>
            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="statusByTypeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Resolution Performance by Incident Type</strong>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Incident Type</th>
                    <th>Total Cases</th>
                    <th>Resolved</th>
                    <th>Resolution Rate</th>
                    <th>Avg. Resolution Time</th>
                </tr>
            </thead>

            <tbody>
                @forelse($resolutionByType as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['label'] }}</td>
                        <td>{{ number_format($row['total']) }}</td>
                        <td>{{ number_format($row['resolved']) }}</td>
                        <td>{{ number_format($row['resolution_rate'], 1) }}%</td>
                        <td>{{ number_format($row['avg_resolution_days'], 1) }} days</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No incident data matches the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <strong>Recent Incidents</strong>
            <div class="small text-muted">
                Latest records matching the selected filters.
            </div>
        </div>

        <a href="{{ route('blotter.index') }}" class="btn btn-sm btn-outline-primary">
            View Blotter Records
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    <th>Incident Type</th>
                    <th>Date / Time</th>
                    <th>Location</th>
                    <th>Stage</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                @forelse($recentIncidents as $case)
                    <tr>
                        <td>
                            <a
                                href="{{ route('blotter.show', $case) }}"
                                class="text-decoration-none fw-semibold"
                            >
                                {{ $case->reference_number }}
                            </a>
                        </td>
                        <td>{{ $case->incidentType?->name ?? '—' }}</td>
                        <td>
                            {{ $case->incident_date?->format('M d, Y') ?? '—' }}
                            @if($case->incident_time)
                                <div class="small text-muted">
                                    {{ date('h:i A', strtotime($case->incident_time)) }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $case->location ?: '—' }}</td>
                        <td>
                            <span class="badge {{ $case->case_stage?->badgeClass() ?? 'text-bg-secondary' }}">
                                {{
                                    $case->case_stage instanceof AppEnumsCaseStage
                                        ? $case->case_stage->label()
                                        : ($case->case_stage ?? 'New')
                                }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $case->record_status?->badgeClass() ?? 'text-bg-primary' }}">
                                {{ $case->record_status?->value ?? 'Open' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No incidents match the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const monthlyTrend = @json(
        $monthlyTrend->map(fn ($row) => [
            'label' => $row->period,
            'total' => (int) $row->total,
        ])->values()
    );

    const incidentTypes = @json(
        $incidentTypeDistribution->map(fn ($row) => [
            'label' => $row->name,
            'total' => (int) $row->total,
        ])->values()
    );

    const dayOfWeek = @json(
        $dayOfWeek->map(fn ($row) => [
            'label' => $row->day_name,
            'total' => (int) $row->total,
        ])->values()
    );

    const timeOfDay = @json(
        $timeOfDay->map(fn ($row) => [
            'label' => $row->time_bucket,
            'total' => (int) $row->total,
        ])->values()
    );

    const locations = @json(
        $topLocations->map(fn ($row) => [
            'label' => $row->location,
            'total' => (int) $row->total,
        ])->values()
    );

    const statusByType = @json($statusByType);

    function barChart(id, rows, horizontal = false) {
        const canvas = document.getElementById(id);
        if (! canvas) return;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(row => row.label),
                datasets: [{
                    label: 'Incidents',
                    data: rows.map(row => row.total),
                    backgroundColor: 'rgba(37, 99, 235, 0.78)',
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: { beginAtZero: true },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }

    const monthlyCanvas = document.getElementById('monthlyTrendChart');
    if (monthlyCanvas) {
        new Chart(monthlyCanvas, {
            type: 'line',
            data: {
                labels: monthlyTrend.map(row => row.label),
                datasets: [{
                    label: 'Incidents',
                    data: monthlyTrend.map(row => row.total),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.10)',
                    fill: true,
                    tension: 0.28,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }

    barChart('incidentTypeChart', incidentTypes, true);
    barChart('dayOfWeekChart', dayOfWeek);
    barChart('timeOfDayChart', timeOfDay);
    barChart('locationChart', locations, true);

    const statusCanvas = document.getElementById('statusByTypeChart');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'bar',
            data: {
                labels: statusByType.map(row => row.label),
                datasets: [
                    {
                        label: 'Open',
                        data: statusByType.map(row => row.open),
                        backgroundColor: 'rgba(37, 99, 235, 0.80)',
                    },
                    {
                        label: 'Resolved',
                        data: statusByType.map(row => row.resolved),
                        backgroundColor: 'rgba(34, 197, 94, 0.75)',
                    },
                    {
                        label: 'Closed',
                        data: statusByType.map(row => row.closed),
                        backgroundColor: 'rgba(100, 116, 139, 0.75)',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }
});
</script>
@endpush
