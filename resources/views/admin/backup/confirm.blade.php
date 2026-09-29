@extends('layouts.app')

@section('title', 'Confirm Restore')
@section('page-title', 'Confirm Restore')

@section('content')
@php
    $labels = [
        'roles' => 'Roles',
        'users' => 'System Users',
        'residents' => 'People Directory',
        'incident_types' => 'Incident Types',
        'blotter_cases' => 'Blotter Cases',
        'case_complainants' => 'Complainants',
        'case_respondents' => 'Respondents',
        'case_witnesses' => 'Witnesses',
        'case_assignments' => 'Case Assignments',
        'investigation_notes' => 'Investigation Notes',
        'mediation_sessions' => 'Hearings',
        'mediation_attendees' => 'Hearing Attendees',
        'mediation_summons' => 'Summons',
        'mediation_outcomes' => 'Mediation Outcomes',
        'case_resolutions' => 'Settlements',
        'audit_logs' => 'Audit Logs',
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Confirm Database Restore</h3>
        <div class="text-muted">
            The uploaded backup passed encryption, format, schema, account, and record-count validation.
        </div>
    </div>

    <a
        href="{{ route('admin.backup.index') }}"
        class="btn btn-outline-secondary"
    >
        Cancel
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="alert alert-danger">
    <div class="fw-bold mb-1">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Destructive operation
    </div>
    Restoring replaces the current persistent application data with the validated backup.
    A pre-restore safety backup will be generated immediately before the transaction begins.
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Validated Backup</strong>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="text-muted small">Application</div>
                <div class="fw-semibold">
                    {{ $backup['application'] ?: 'Barangay San Jose Blotter Management System' }}
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="text-muted small">Backup Created</div>
                <div class="fw-semibold">
                    {{ $backup['created_at'] ?: 'Unknown' }}
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="text-muted small">Backup Format Version</div>
                <div class="fw-semibold">
                    {{ $backup['version'] }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Backup vs Current Record Counts</strong>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data Group</th>
                    <th class="text-end">Current</th>
                    <th class="text-end">Backup</th>
                </tr>
            </thead>
            <tbody>
                @foreach($labels as $table => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-end">
                            {{ number_format($currentCounts[$table] ?? 0) }}
                        </td>
                        <td class="text-end fw-semibold">
                            {{ number_format($backup['counts'][$table] ?? 0) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm border-danger">
    <div class="card-header bg-white">
        <strong>Final Confirmation</strong>
    </div>

    <div class="card-body">
        <form
            method="POST"
            action="{{ route('admin.backup.restore') }}"
        >
            @csrf

            <input
                type="hidden"
                name="token"
                value="{{ $token }}"
            >

            <div class="mb-3">
                <label
                    for="confirmation"
                    class="form-label"
                >
                    Type <strong>RESTORE</strong>
                </label>

                <input
                    id="confirmation"
                    type="text"
                    name="confirmation"
                    class="form-control"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="mb-3">
                <label
                    for="current_password"
                    class="form-label"
                >
                    Current Barangay Captain Password
                </label>

                <input
                    id="current_password"
                    type="password"
                    name="current_password"
                    class="form-control"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button
                    type="submit"
                    class="btn btn-danger"
                    onclick="return confirm('Proceed with database restore? The current application data will be replaced.')"
                >
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Restore Backup
                </button>

                <a
                    href="{{ route('admin.backup.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
