<div class="card mb-4">
    <div class="card-header"><strong>{{ $title }}</strong><span class="small text-muted ms-2">Up to 10 matching cases</span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th scope="col">Reference</th><th scope="col">Incident Type</th>
                <th scope="col">{{ $aging ? 'Reported' : 'Incident Date' }}</th>
                <th scope="col">Current Stage</th><th scope="col">{{ $aging ? 'Age / Target' : 'Record Status' }}</th>
                <th scope="col">Current Councilor</th>
            </tr></thead>
            <tbody>
                @forelse($cases as $case)
                    <tr>
                        <td><a href="{{ route('cases.show', $case) }}" class="fw-semibold">{{ $case->reference_number }}</a></td>
                        <td>{{ $case->incidentType?->name ?? '—' }}</td>
                        <td>{{ ($aging ? $case->reported_at : $case->incident_date)?->format('M d, Y') ?? '—' }}</td>
                        <td><span class="badge {{ $case->case_stage?->badgeClass() ?? 'text-bg-secondary' }}">{{ $case->case_stage?->label() ?? '—' }}</span></td>
                        <td>
                            @if($aging)
                                <span class="badge {{ $targetDays !== null && $case->age_days > $targetDays ? 'text-bg-danger' : 'text-bg-light' }}">
                                    {{ number_format($case->age_days) }} days
                                </span>
                                @if($targetDays !== null && $case->age_days > $targetDays)
                                    <div class="small text-danger mt-1">{{ number_format($case->age_days - $targetDays) }} days beyond target</div>
                                @endif
                            @else
                                <span class="badge {{ $case->record_status?->badgeClass() ?? 'text-bg-secondary' }}">{{ $case->record_status?->value ?? '—' }}</span>
                            @endif
                        </td>
                        <td>{{ $case->currentAssignment?->assignedOfficer?->name ?? 'Unassigned' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ $aging ? 'No open cases with usable report dates match these filters.' : 'No cases match the selected filters.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
