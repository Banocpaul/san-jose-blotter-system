<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay San Jose Audit Logs</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 28px; color: #111; font-size: 11px; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h1 { margin: 0 0 4px; font-size: 20px; }
        .header p { margin: 2px 0; }
        .toolbar { margin-bottom: 16px; display: flex; gap: 8px; }
        .toolbar button { padding: 8px 12px; border: 1px solid #111; background: #fff; cursor: pointer; }
        .filters { border: 1px solid #bbb; padding: 9px; margin-bottom: 14px; }
        .filters span { display: inline-block; margin: 0 14px 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #888; padding: 6px; vertical-align: top; text-align: left; }
        th { background: #efefef; }
        .small { font-size: 9px; color: #555; }
        .footer { margin-top: 14px; font-size: 9px; color: #555; }
        @media print {
            @page { size: landscape; margin: 8mm; }
            body { margin: 0; }
            .no-print { display: none !important; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Audit Logs</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>

    <div class="header">
        <h1>Barangay San Jose</h1>
        <p><strong>Blotter Management System</strong></p>
        <p>Audit Logs</p>
    </div>

    <div class="filters">
        @foreach($filters as $label => $value)
            <span><strong>{{ $label }}:</strong> {{ $value }}</span>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Date / Time</th>
                <th>User</th>
                <th>Role</th>
                <th>Action</th>
                <th>Module</th>
                <th>Description</th>
                <th>IP Address</th>
                <th>Related Record</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d h:i:s A') ?? '—' }}</td>
                    <td>
                        {{ $log->user?->name ?? 'System / Deleted User' }}
                        @if($log->user?->username)
                            <div class="small">{{ $log->user->username }}</div>
                        @endif
                    </td>
                    <td>{{ $log->user?->role?->name ?? '—' }}</td>
                    <td>{{ ucfirst($log->action) }}</td>
                    <td>{{ $log->module ?? '—' }}</td>
                    <td>{{ $log->description ?? '—' }}</td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                    <td>
                        @if($log->auditable_type || $log->auditable_id)
                            {{ class_basename($log->auditable_type ?? '') }}
                            @if($log->auditable_id)
                                #{{ $log->auditable_id }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;">No matching audit records.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated {{ $generatedAt->format('M d, Y h:i A') }} • {{ number_format($logs->count()) }} record(s)
    </div>
</body>
</html>
