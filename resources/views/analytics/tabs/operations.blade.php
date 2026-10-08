<div class="row g-3 mb-4">
    @include('analytics.partials.metric', ['label' => 'Open Cases', 'value' => number_format($openCases), 'description' => 'Currently in process', 'icon' => 'bi-folder2-open', 'tone' => 'metric-dark'])
    @include('analytics.partials.metric', ['label' => 'New', 'value' => number_format($stageSummary->firstWhere('stage', 'New')['total']), 'description' => 'Open cases awaiting intake', 'icon' => 'bi-plus-lg'])
    @include('analytics.partials.metric', ['label' => 'Under Assessment', 'value' => number_format($stageSummary->firstWhere('stage', 'Under Assessment')['total']), 'description' => 'Open cases under review', 'icon' => 'bi-file-earmark-text'])
    @include('analytics.partials.metric', ['label' => 'For Mediation', 'value' => number_format($stageSummary->firstWhere('stage', 'For Mediation')['total']), 'description' => 'Open mediation workload', 'icon' => 'bi-chat-square-text'])
    @include('analytics.partials.metric', ['label' => 'Pending Hearings', 'value' => number_format($pendingHearings->count()), 'description' => 'Scheduled, not completed', 'icon' => 'bi-people'])
    @include('analytics.partials.metric', ['label' => 'Avg. Days in Current Stage', 'value' => $avgCurrentStageDays === null ? '—' : number_format($avgCurrentStageDays, 1), 'description' => $currentStageSamples.' active cases with recorded entry dates', 'icon' => 'bi-clock'])
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-4">@include('analytics.partials.dashboard-chart', ['id' => 'operationStatus', 'title' => 'Case Status Distribution', 'description' => 'Current record status across all matching cases.', 'rows' => $statusDistribution, 'chartType' => 'doughnut'])</div>
    <div class="col-xl-4">@include('analytics.partials.dashboard-chart', ['id' => 'operationStages', 'title' => 'Cases by Current Stage', 'description' => 'Open cases only, grouped by current workflow stage.', 'rows' => $stageSummary, 'horizontal' => true, 'byRow' => true])</div>
    <div class="col-xl-4">@include('analytics.partials.dashboard-chart', ['id' => 'operationFiling', 'title' => 'Cases Filed by Month', 'description' => 'Report dates in '.$periodLabel.'. Future filings are excluded.', 'rows' => $filedTrend])</div>
    <div class="col-xl-4">@include('analytics.partials.dashboard-chart', ['id' => 'operationTypes', 'title' => 'Incident Type Distribution', 'description' => 'Incident types across all matching cases.', 'rows' => $incidentDistribution, 'horizontal' => true, 'byRow' => true])</div>
    <div class="col-xl-4"><div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center gap-2"><strong>Pending Hearings / Mediation</strong><a href="{{ route('hearings.index') }}" class="btn btn-outline-secondary btn-sm">View all</a></div>
        <div class="card-body"><p class="small text-muted">Scheduled proceedings on matching open cases. Past schedules stay visible until their status is updated.</p>
            @forelse($pendingHearings->take(4) as $session)
                <div class="analytics-hearing d-flex gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="analytics-date"><span>{{ $session->scheduled_date->format('M') }}</span><strong>{{ $session->scheduled_date->format('d') }}</strong></div>
                    <div class="flex-grow-1"><strong>{{ $session->proceeding_type ?: 'Mediation' }}</strong>
                        <div class="small"><a href="{{ route('cases.show', $session->blotterCase) }}">{{ $session->blotterCase->reference_number }}</a></div>
                        <div class="small text-muted">{{ $session->blotterCase->incidentType?->name }}</div>
                        <div class="small text-muted"><i class="bi bi-clock" aria-hidden="true"></i> {{ $session->scheduled_time ?: 'Time not recorded' }} PHT · {{ $session->venue }}</div>
                        @if($session->dashboard_overdue)<span class="badge text-bg-warning mt-1">Past schedule · needs review</span>@endif
                    </div>
                </div>
            @empty<div class="analytics-empty text-muted">No scheduled hearings match these open cases.</div>@endforelse
        </div>
    </div></div>
    <div class="col-xl-4">@include('analytics.partials.dashboard-chart', ['id' => 'operationStageAge', 'title' => 'Average Days in Current Stage', 'description' => 'Elapsed calendar days on active cases with recorded stage entry. Missing dates appear as unavailable.', 'rows' => $stageAverageRows, 'byRow' => true, 'unit' => 'days', 'valueLabel' => 'Average days'])</div>
</div>
@include('analytics.partials.snapshot', ['title' => 'Active Case Snapshot', 'description' => 'Up to five matching open cases, ordered by known days in their current stage.', 'cases' => $activeSnapshot])
<details class="mb-4"><summary class="fw-semibold mb-3">Additional Operations Metrics</summary>
    <div class="row g-3"><div class="col-xl-6">@include('analytics.partials.dashboard-chart', ['id' => 'operationSitios', 'title' => 'Cases Involving Each Sitio', 'description' => 'Party addresses; each case counts once per Sitio and may involve several Sitios.', 'rows' => $sitioDistribution])</div>
    <div class="col-xl-6">@include('analytics.partials.dashboard-chart', ['id' => 'operationCouncilors', 'title' => 'Current Councilor Workload', 'description' => 'Open cases under their current assigned councilor.', 'rows' => $councilorWorkload, 'horizontal' => true])</div></div>
</details>
