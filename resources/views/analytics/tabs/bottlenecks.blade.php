@if(in_array(auth()->user()->role?->slug, ['barangay_captain', 'secretary'], true))
    <div class="d-flex justify-content-end mb-3"><a href="{{ route('settings.sla.edit') }}" class="btn btn-outline-primary"><i class="bi bi-gear" aria-hidden="true"></i> SLA Settings</a></div>
@endif
<div class="row g-3 mb-4">
    @include('analytics.partials.metric', ['label' => 'Cases Within SLA', 'value' => number_format($withinSla), 'description' => 'Under '.$slaPolicy['near_percent'].'% of the stage allowance', 'icon' => 'bi-clock'])
    @include('analytics.partials.metric', ['label' => 'Near SLA', 'value' => number_format($nearSla), 'description' => $slaPolicy['near_percent'].'–100% of the allowance used', 'icon' => 'bi-exclamation-triangle', 'tone' => 'metric-warning'])
    @include('analytics.partials.metric', ['label' => 'Beyond SLA', 'value' => number_format($beyondSla), 'description' => 'Past the recorded stage deadline', 'icon' => 'bi-exclamation-circle', 'tone' => 'metric-danger'])
    @include('analytics.partials.metric', ['label' => 'Avg. Processing Time', 'value' => $periodAverageResolution === null ? '—' : number_format($periodAverageResolution, 1).' days', 'description' => 'Report to resolution in '.$periodLabel, 'icon' => 'bi-stopwatch'])
    @include('analytics.partials.metric', ['label' => 'Longest Pending Case', 'value' => $longestPending === null ? '—' : number_format($longestPending->dashboard_age_days, 1).' days', 'description' => $longestPending?->reference_number ?? 'No usable report date', 'icon' => 'bi-hourglass-split'])
    @include('analytics.partials.metric', ['label' => 'Stage with Highest Delay', 'value' => $highestDelay['label'] ?? '—', 'description' => isset($highestDelay['avg_days']) ? 'Avg. '.$highestDelay['avg_days'].' calendar days currently in stage' : 'No recorded stage entry dates', 'icon' => 'bi-layers'])
</div>
<div class="alert alert-light border mb-4" role="status"><strong>{{ number_format($unknownSla) }} open cases have unavailable SLA measurements.</strong> Missing start dates are excluded from within/near/beyond counts. Stage age and overall case age use different starting dates.</div>
<div class="row g-3 mb-4">
    <div class="col-xl-5">@include('analytics.partials.dashboard-chart', ['id' => 'slaWorkflow', 'title' => 'SLA by Workflow Stage', 'description' => 'Exclusive SLA groups across matching open cases. Gray indicates unavailable dates.', 'rows' => $slaStageRows, 'series' => ['Within SLA' => 'Within SLA', 'Near SLA' => 'Near SLA', 'Beyond SLA' => 'Beyond SLA', 'Unavailable' => 'Unavailable'], 'stacked' => true, 'colors' => ['#2563eb', '#fbbf24', '#ef6464', '#94a3b8']])</div>
    <div class="col-xl-3">@include('analytics.partials.dashboard-chart', ['id' => 'slaDelayed', 'title' => 'Delayed Cases by Stage', 'description' => 'Open cases past their recorded stage deadline.', 'rows' => $delayedStageRows, 'horizontal' => true, 'colors' => ['#ef6464']])</div>
    <div class="col-xl-4">@include('analytics.partials.dashboard-chart', ['id' => 'slaProcessing', 'title' => 'Open-to-Resolved Processing Time', 'description' => 'Report-to-resolution duration for valid resolved records in '.$periodLabel.'.', 'rows' => $processingDistribution])</div>
    <div class="col-xl-5">@include('analytics.partials.dashboard-chart', ['id' => 'slaAging', 'title' => 'Aging of Open Cases', 'description' => 'Whole elapsed calendar days from the report date. Stage SLA targets are evaluated separately.', 'rows' => $agingDistribution])</div>
    <div class="col-xl-7"><div class="card h-100"><div class="card-header"><strong>Delay Heat Summary</strong></div><div class="card-body">
        <p class="small text-muted">Average current-stage age uses known entry dates. Status reflects recorded stage SLA measurements.</p>
        <div class="analytics-delay-grid">@foreach($stageSummary as $stage)
            @php($state = $stage['beyond'] > 0 ? 'Beyond SLA' : ($stage['near'] > 0 ? 'Near SLA' : ($stage['within'] > 0 ? 'Within SLA' : 'Unavailable')))
            <div class="analytics-delay-cell {{ $stage['beyond'] ? 'delay-danger' : ($stage['near'] ? 'delay-warning' : '') }}">
                <div class="small fw-semibold">{{ $stage['label'] }}</div><div class="fw-bold my-2">{{ $stage['avg_days'] === null ? '—' : $stage['avg_days'].' days' }}</div>
                <span class="badge {{ match ($state) { 'Beyond SLA' => 'text-bg-danger', 'Near SLA' => 'text-bg-warning', 'Within SLA' => 'text-bg-success', default => 'text-bg-secondary' } }}">{{ $state }}</span>
                <div class="small text-muted mt-2">{{ $stage['beyond'] }} delayed · {{ $stage['unknown'] }} unavailable</div>
                <div class="small text-muted">{{ $stage['samples'] }} known entry dates</div>
            </div>
        @endforeach</div>
    </div></div></div>
</div>
@include('analytics.partials.snapshot', ['title' => 'Bottleneck Case List', 'description' => 'Up to ten near/beyond SLA cases, ordered by known overall open-case age (highest first).', 'cases' => $bottleneckCases, 'bottleneck' => true, 'emptyMessage' => 'No cases with recorded SLA dates are near or beyond their deadline.'])
@if($untrackedCases->isNotEmpty())
    <div class="card mb-4"><div class="card-header"><strong>Cases with Unavailable SLA Dates</strong></div><div class="card-body">
        <p class="small text-muted">Up to five matching cases needing a reliable start date.</p>
        @foreach($untrackedCases as $case)<div class="d-flex flex-wrap justify-content-between gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}"><a href="{{ route('cases.show', $case) }}">{{ $case->reference_number }}</a><span>{{ $case->case_stage?->label() }}</span><span class="text-muted small">SLA start unavailable</span></div>@endforeach
    </div></div>
@endif
@if(in_array(auth()->user()->role?->slug, ['barangay_captain', 'secretary'], true))
<details class="card mb-4"><summary class="card-header"><strong>Record Actual SLA Dates & Approved Extensions</strong></summary><div class="card-body">
    <p class="small text-muted">Record confirmed events from the case file. First meeting dates start mediation/Pangkat clocks; eligibility starts further-action timing. Each entry is recorded in the audit log.</p>
    <div class="row g-4">
        <div class="col-lg-6"><form method="POST" action="{{ route('analytics.sla.start') }}">@csrf
            <h6>Record actual start date</h6>
            <label for="sla_start_case" class="form-label">Case</label><select id="sla_start_case" name="case_id" class="form-select mb-3" required @disabled($clockCandidates->isEmpty())><option value="">Choose a case</option>@foreach($clockCandidates as $case)<option value="{{ $case->id }}">{{ $case->reference_number }} · {{ $case->case_stage->label() }}</option>@endforeach</select>
            <label for="sla_started_at" class="form-label">Actual meeting / eligibility date and time (PHT)</label><input id="sla_started_at" type="datetime-local" name="started_at" value="{{ old('started_at') }}" max="{{ $asOf->setTimezone('Asia/Manila')->format('Y-m-d\TH:i') }}" class="form-control mb-3" required>
            <label for="sla_start_reason" class="form-label">Source or confirmation from the case file</label><textarea id="sla_start_reason" name="reason" minlength="10" maxlength="1000" rows="2" class="form-control mb-3" required></textarea>
            <button class="btn btn-primary" type="submit" @disabled($clockCandidates->isEmpty())>Record Start Date</button>
            @if($clockCandidates->isEmpty())<p class="small text-muted mt-2">No matching eligible cases need a start date.</p>@endif
        </form></div>
        <div class="col-lg-6"><form method="POST" action="{{ route('analytics.sla.extension') }}">@csrf
            <h6>Record a Pangkat-approved extension</h6>
            <label for="sla_extension_case" class="form-label">Pangkat case</label><select id="sla_extension_case" name="case_id" class="form-select mb-3" required @disabled($extensionCandidates->isEmpty())><option value="">Choose a case</option>@foreach($extensionCandidates as $case)<option value="{{ $case->id }}">{{ $case->reference_number }}</option>@endforeach</select>
            <label for="sla_extension_reason" class="form-label">Approved decision reference and reason</label><textarea id="sla_extension_reason" name="reason" minlength="10" maxlength="1000" rows="4" class="form-control mb-3" required></textarea>
            <p class="small text-muted">Records one additional 15-calendar-day allowance. Enter this only after the Pangkat has approved the extension.</p>
            <button class="btn btn-outline-primary" type="submit" @disabled($extensionCandidates->isEmpty())>Record Approved 15-Day Extension</button>
        </form></div>
    </div>
</div></details>
@endif
<details class="card mb-4"><summary class="card-header"><strong>SLA Targets & Measurement</strong></summary><div class="card-body">
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th scope="col">Stage</th><th scope="col">Target</th><th scope="col">Clock starts at</th></tr></thead><tbody>
        @foreach($slaPolicy['targets'] as $stage => $target)<tr><td>{{ $stage }}</td><td>{{ $target['days'] }} {{ $target['unit'] }} {{ $target['days'] === 1 ? 'day' : 'days' }}</td><td>{{ $target['start'] }}</td></tr>@endforeach
    </tbody></table></div>
    <p class="small text-muted">Within: under {{ $slaPolicy['near_percent'] }}% used. Near: {{ $slaPolicy['near_percent'] }}–100% used. Beyond: deadline passed. Working-day targets exclude weekends and the applicable non-working dates recorded for the barangay. {{ count($slaPolicy['non_working_dates']) }} holiday dates are currently configured.</p>
    <p class="small text-muted">These are operational monitoring targets. The settings control internal analysis targets. Changes apply to current open cases from their recorded start dates. Mediation and Pangkat use actual first-meeting dates; a scheduled meeting alone does not start the clock. An extension affects only its recorded Pangkat case.</p>
    <p class="small text-muted mb-0">Optional overall case-aging target: {{ $targetDays === null ? 'Not set' : $targetDays.' calendar days' }}. {{ $targetDays === null ? '' : $beyondTargetCases.' open cases exceed that separate report-date target.' }}</p>
</div></details>
