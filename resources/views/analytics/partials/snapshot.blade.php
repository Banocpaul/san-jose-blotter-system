@php
    $nextSteps = [
        'New' => 'Review intake and prepare summons',
        'Under Assessment' => 'Review case details',
        'For Mediation' => 'Coordinate mediation',
        'For Pangkat/Conciliation' => 'Coordinate Pangkat proceedings',
        'For Further Action/CFA' => 'Review eligible referral or CFA action',
    ];
@endphp
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><strong>{{ $title }}</strong><div class="small text-muted">{{ $description }}</div></div>
        <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary btn-sm">View all cases</a>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th scope="col">Reference</th>@if(empty($bottleneck))<th scope="col">Incident</th>@endif
            <th scope="col">Current Stage</th><th scope="col">{{ !empty($bottleneck) ? 'Days Open' : 'Days in Stage' }}</th>
            @if(!empty($bottleneck))<th scope="col">SLA Status / Due</th><th scope="col">Last Recorded Action</th>@endif
            <th scope="col">Suggested Next Step</th>@if(empty($bottleneck))<th scope="col">Next Schedule (PHT)</th>@endif</tr></thead>
        <tbody>@forelse($cases as $case)
            <tr><td><a class="fw-semibold" href="{{ route('cases.show', $case) }}">{{ $case->reference_number }}</a></td>
                @if(empty($bottleneck))<td>{{ $case->incidentType?->name ?? '—' }}</td>@endif
                <td><span class="badge {{ $case->case_stage?->badgeClass() }}">{{ $case->case_stage?->label() ?? 'Unavailable' }}</span></td>
                <td>@php($days = !empty($bottleneck) ? $case->dashboard_age_days : $case->dashboard_stage_days)
                    {{ $days === null ? 'Unavailable' : number_format($days, 1).' days' }}</td>
                @if(!empty($bottleneck))
                    <td><span class="badge {{ match ($case->dashboard_sla['status']) { 'Beyond SLA' => 'text-bg-danger', 'Near SLA' => 'text-bg-warning', 'Within SLA' => 'text-bg-success', default => 'text-bg-secondary' } }}">{{ $case->dashboard_sla['status'] }}</span>
                        <div class="small text-muted mt-1">{{ $case->dashboard_sla['due_at']?->format('M d, Y · h:i A').' PHT' }}</div>
                        @if($case->sla_extension_days)<div class="small text-muted">15-day extension recorded</div>@endif
                    </td>
                    <td>{{ $case->dashboard_last_action['text'] ?? 'Unavailable' }}
                        @if($case->dashboard_last_action['date'] ?? null)<div class="small text-muted">{{ $case->dashboard_last_action['date']->copy()->setTimezone('Asia/Manila')->format('M d, Y') }}</div>@endif
                    </td>
                @endif
                <td>{{ $nextSteps[$case->case_stage?->value] ?? 'Review current case status' }}</td>
                @if(empty($bottleneck))<td>@if($case->dashboardNextSession)
                    {{ $case->dashboardNextSession->scheduled_date->format('M d, Y') }}<br><span class="small text-muted">{{ $case->dashboardNextSession->scheduled_time ?: 'Time not recorded' }}</span>
                @else<span class="text-muted">No upcoming schedule</span>@endif</td>@endif
            </tr>
        @empty<tr><td colspan="{{ !empty($bottleneck) ? 6 : 6 }}" class="text-center text-muted py-4">{{ $emptyMessage ?? 'No active cases match the filters.' }}</td></tr>@endforelse</tbody>
    </table></div>
</div>
