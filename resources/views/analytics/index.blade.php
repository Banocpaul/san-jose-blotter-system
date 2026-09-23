@extends('layouts.app')

@section('title', 'Business Intelligence')
@section('page-title', 'Business Intelligence')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h3 class="mb-1">Business Intelligence</h3>
        <div class="text-muted">
            Filterable blotter analytics for Barangay San Jose.
        </div>
    </div>

    <div class="small text-muted">
        {{ number_format($totalCases) }} matching case(s)
    </div>
</div>


{{-- ========================================================= --}}
{{-- FILTERS --}}
{{-- ========================================================= --}}

<div class="card mb-4">
    <div class="card-header">
        <strong>Analytics Filters</strong>
    </div>

    <div class="card-body">
        <form
            method="GET"
            action="{{ route('analytics.index') }}"
        >
            <div class="row g-3">

                <div class="col-md-2">
                    <label class="form-label">
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        value="{{ request('date_from') }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-2">
                    <label class="form-label">
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        value="{{ request('date_to') }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-2">
                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >
                        <option value="">
                            All Statuses
                        </option>

                        @foreach($statuses as $status)
                            <option
                                value="{{ $status->value }}"
                                @selected(
                                    request('status')
                                    ===
                                    $status->value
                                )
                            >
                                {{ $status->value }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Incident Type
                    </label>

                    <select
                        name="incident_type_id"
                        class="form-select"
                    >
                        <option value="">
                            All Incident Types
                        </option>

                        @foreach($incidentTypes as $type)
                            <option
                                value="{{ $type->id }}"
                                @selected(
                                    (string) request(
                                        'incident_type_id'
                                    )
                                    ===
                                    (string) $type->id
                                )
                            >
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Sitio
                    </label>

                    <select
                        name="sitio"
                        class="form-select"
                    >
                        <option value="">
                            All Sitios
                        </option>

                        @foreach($sitios as $sitio)
                            <option
                                value="{{ $sitio }}"
                                @selected(
                                    request('sitio')
                                    ===
                                    $sitio
                                )
                            >
                                {{ $sitio }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Assigned Councilor
                    </label>

                    <select
                        name="councilor_id"
                        class="form-select"
                    >
                        <option value="">
                            All Councilors
                        </option>

                        @foreach($councilors as $councilor)
                            <option
                                value="{{ $councilor->id }}"
                                @selected(
                                    (string) request(
                                        'councilor_id'
                                    )
                                    ===
                                    (string) $councilor->id
                                )
                            >
                                {{ $councilor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Mediation Outcome
                    </label>

                    <select
                        name="mediation_outcome"
                        class="form-select"
                    >
                        <option value="">
                            All Outcomes
                        </option>

                        @foreach($mediationOutcomes as $outcome)
                            <option
                                value="{{ $outcome }}"
                                @selected(
                                    request(
                                        'mediation_outcome'
                                    )
                                    ===
                                    $outcome
                                )
                            >
                                {{ $outcome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-funnel me-1"></i>
                        Apply Filters
                    </button>

                    <a
                        href="{{ route('analytics.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>


{{-- ========================================================= --}}
{{-- KPI CARDS --}}
{{-- ========================================================= --}}

<div class="row g-3 mb-4">

    <div class="col-md-6 col-xl">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">
                    Total Cases
                </div>

                <div class="fs-3 fw-semibold">
                    {{ number_format($totalCases) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">
                    Open Cases
                </div>

                <div class="fs-3 fw-semibold">
                    {{ number_format($openCases) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">
                    Closed Cases
                </div>

                <div class="fs-3 fw-semibold">
                    {{ number_format($closedCases) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">
                    Settlement Rate
                </div>

                <div class="fs-3 fw-semibold">
                    {{ number_format($settlementRate, 1) }}%
                </div>

                <div class="text-muted small mt-1">
                    Settled ÷ closed cases
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-2">
                    Avg. Resolution
                </div>

                <div class="fs-3 fw-semibold">
                    {{ number_format($avgResolutionDays, 1) }}
                </div>

                <div class="text-muted small mt-1">
                    Days
                </div>
            </div>
        </div>
    </div>

</div>


{{-- ========================================================= --}}
{{-- CHARTS --}}
{{-- ========================================================= --}}

<div class="row g-4 mb-4">

    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <strong>Cases Over Time</strong>
            </div>

            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="caseTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <strong>Case Status Distribution</strong>
            </div>

            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>


<div class="row g-4 mb-4">

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Incident Types</strong>
            </div>

            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="incidentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Cases by Sitio</strong>
            </div>

            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="sitioChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>


<div class="row g-4 mb-4">

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Councilor Case Workload</strong>
            </div>

            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="councilorChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <strong>Mediation Outcomes</strong>
            </div>

            <div class="card-body">
                <div style="height: 340px;">
                    <canvas id="mediationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>


{{-- ========================================================= --}}
{{-- RECENT MATCHING CASES --}}
{{-- ========================================================= --}}

<div class="card">
    <div class="card-header">
        <strong>Recent Matching Cases</strong>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Incident Type</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Assigned Councilor</th>
                </tr>
            </thead>

            <tbody>
                @forelse($recentCases as $case)
                    <tr>
                        <td>
                            <a
                                href="{{ route('blotter.show', $case) }}"
                                class="text-decoration-none fw-semibold"
                            >
                                {{ $case->reference_number }}
                            </a>
                        </td>

                        <td>
                            {{ $case->incidentType?->name ?? '—' }}
                        </td>

                        <td>
                            {{
                                $case->incident_date
                                    ?->format('M d, Y')
                                ?? '—'
                            }}
                        </td>

                        <td>
                            {{ $case->status->value ?? $case->status }}
                        </td>

                        <td>
                            {{
                                $case
                                    ->currentAssignment
                                    ?->assignedOfficer
                                    ?->name
                                ?? 'Unassigned'
                            }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="5"
                            class="text-center text-muted py-4"
                        >
                            No cases match the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection


@push('scripts')

<script
    src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
></script>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        const caseTrend =
            @json(
                $caseTrend
                    ->map(
                        fn ($row) => [
                            'label' => $row->period,
                            'total' => (int) $row->total,
                        ]
                    )
                    ->values()
            );

        const statusData =
            @json($statusDistribution);

        const incidentData =
            @json(
                $incidentDistribution
                    ->map(
                        fn ($row) => [
                            'label' => $row->name,
                            'total' => (int) $row->total,
                        ]
                    )
                    ->values()
            );

        const sitioData =
            @json($sitioDistribution);

        const councilorData =
            @json($councilorWorkload);

        const mediationData =
            @json($mediationDistribution);


        function makeBarChart(
            elementId,
            rows,
            horizontal = false
        ) {
            const canvas =
                document.getElementById(
                    elementId
                );

            if (!canvas) {
                return;
            }

            new Chart(
                canvas,
                {
                    type: 'bar',

                    data: {
                        labels:
                            rows.map(
                                row => row.label
                            ),

                        datasets: [
                            {
                                label: 'Cases',

                                data:
                                    rows.map(
                                        row => row.total
                                    ),

                                backgroundColor:
                                    'rgba(37, 99, 235, 0.78)',

                                borderColor:
                                    'rgba(17, 24, 39, 1)',

                                borderWidth: 1,

                                borderRadius: 6,
                            }
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        indexAxis:
                            horizontal
                                ? 'y'
                                : 'x',

                        plugins: {
                            legend: {
                                display: false,
                            },
                        },

                        scales: {
                            y: {
                                beginAtZero: true,

                                ticks: {
                                    precision: 0,
                                },
                            },

                            x: {
                                beginAtZero: true,
                            },
                        },
                    },
                }
            );
        }


        const trendCanvas =
            document.getElementById(
                'caseTrendChart'
            );

        if (trendCanvas) {
            new Chart(
                trendCanvas,
                {
                    type: 'line',

                    data: {
                        labels:
                            caseTrend.map(
                                row => row.label
                            ),

                        datasets: [
                            {
                                label: 'Cases',

                                data:
                                    caseTrend.map(
                                        row => row.total
                                    ),

                                borderColor:
                                    'rgba(17, 24, 39, 1)',

                                backgroundColor:
                                    'rgba(37, 99, 235, 0.10)',

                                fill: true,

                                tension: 0.28,

                                pointRadius: 3,
                            }
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        plugins: {
                            legend: {
                                display: false,
                            },
                        },

                        scales: {
                            y: {
                                beginAtZero: true,

                                ticks: {
                                    precision: 0,
                                },
                            },
                        },
                    },
                }
            );
        }


        const statusCanvas =
            document.getElementById(
                'statusChart'
            );

        if (statusCanvas) {
            new Chart(
                statusCanvas,
                {
                    type: 'doughnut',

                    data: {
                        labels:
                            statusData.map(
                                row => row.label
                            ),

                        datasets: [
                            {
                                data:
                                    statusData.map(
                                        row => row.total
                                    ),

                                backgroundColor: [
                                    '#2563eb',
                                    '#111318',
                                    '#5b8cff',
                                    '#23395d',
                                    '#7aa2ff',
                                    '#59616f',
                                    '#9eb9ff',
                                ],

                                borderWidth: 0,
                            }
                        ],
                    },

                    options: {
                        responsive: true,
                        maintainAspectRatio: false,

                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                        },
                    },
                }
            );
        }


        makeBarChart(
            'incidentChart',
            incidentData,
            true
        );

        makeBarChart(
            'sitioChart',
            sitioData
        );

        makeBarChart(
            'councilorChart',
            councilorData,
            true
        );

        makeBarChart(
            'mediationChart',
            mediationData,
            true
        );
    }
);
</script>

@endpush
