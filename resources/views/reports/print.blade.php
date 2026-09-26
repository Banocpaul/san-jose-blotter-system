<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay San Jose Case Report</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 28px; color: #111; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 20px; margin: 0 0 5px; }
        .header p { margin: 3px 0; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 18px; }
        .toolbar button { border: 1px solid #111; background: #fff; padding: 8px 12px; cursor: pointer; }
        .filters { margin-bottom: 16px; padding: 10px; border: 1px solid #bbb; }
        .filters span { display: inline-block; margin: 0 16px 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #888; padding: 7px; vertical-align: top; text-align: left; }
        th { background: #efefef; }
        .small { font-size: 10px; color: #444; }
        .footer { margin-top: 16px; font-size: 10px; color: #555; }
        @media print {
            @page { size: landscape; margin: 10mm; }
            body { margin: 0; }
            .no-print { display: none !important; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Report</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>

    <div class="header">
        <h1>Barangay San Jose</h1>
        <p><strong>Blotter Management System</strong></p>
        <p>Case Report</p>
    </div>

    <div class="filters">
        @foreach($filters as $label => $value)
            <span><strong>{{ $label }}:</strong> {{ $value }}</span>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Case No.</th>
                <th>Date</th>
                <th>Incident Type</th>
                <th>Complainant(s)</th>
                <th>Respondent(s)</th>
                <th>Stage</th>
                <th>Status</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cases as $case)
                <tr>
                    <td>{{ $case->reference_number }}</td>
                    <td>{{ $case->incident_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $case->incidentType?->name ?? '—' }}</td>
                    <td>
                        @forelse($case->complainants as $person)
                            <div>
                                {{ trim("{$person->first_name} {$person->middle_name} {$person->last_name} {$person->suffix}") }}
                                <span class="small">({{ $person->is_san_jose_resident ? 'Resident' : 'Non-Resident' }})</span>
                            </div>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>
                        @forelse($case->respondents as $person)
                            <div>
                                {{ trim("{$person->first_name} {$person->middle_name} {$person->last_name} {$person->suffix}") }}
                                <span class="small">({{ $person->is_san_jose_resident ? 'Resident' : 'Non-Resident' }})</span>
                            </div>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>{{ $case->case_stage?->value ?? 'New' }}</td>
                    <td>{{ $case->status?->value ?? (string) $case->status }}</td>
                    <td>{{ $case->location ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;">No matching records.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated {{ $generatedAt->format('M d, Y h:i A') }} • {{ number_format($cases->count()) }} record(s)
    </div>
</body>
</html>
