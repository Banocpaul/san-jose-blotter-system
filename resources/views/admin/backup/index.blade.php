@extends('layouts.app')

@section('title', 'Backup & Restore')
@section('page-title', 'Backup & Restore')

@section('content')
@php
    $labels = [
        'residents' => 'People Directory',
        'blotter_cases' => 'Blotter Cases',
        'case_complainants' => 'Complainants',
        'case_respondents' => 'Respondents',
        'case_witnesses' => 'Witnesses',
        'case_assignments' => 'Case Assignments',
        'investigation_notes' => 'Investigation Notes',
        'mediation_sessions' => 'Hearings',
        'mediation_outcomes' => 'Mediation Outcomes',
        'case_resolutions' => 'Settlements',
        'users' => 'System Users',
        'audit_logs' => 'Audit Logs',
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="mb-1">Backup & Restore</h3>
        <div class="text-muted">
            Create an encrypted local backup or restore validated Barangay San Jose system data.
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Backup & Restore could not complete the request.</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('safety_backup_available') && $safetyToken)
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="fw-semibold">Pre-restore safety backup is ready.</div>
            <div class="small">
                Download it now. The temporary server copy is deleted after download and may also disappear when the server restarts.
            </div>
        </div>

        <a
            href="{{ route('admin.backup.safety-download', ['token' => $safetyToken]) }}"
            class="btn btn-warning"
        >
            <i class="bi bi-download me-1"></i>
            Download Safety Backup
        </a>
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>
                    <i class="bi bi-cloud-arrow-down me-1"></i>
                    Create Backup
                </strong>
            </div>

            <div class="card-body">
                <p class="mb-3">
                    Generate an encrypted <code>.sjbackup</code> file containing the persistent application data.
                    The browser downloads the file to this computer.
                </p>

                <div class="alert alert-info small">
                    The backup does not include <code>.env</code>, database connection passwords,
                    Render secrets, cache, sessions, jobs, or password-reset tokens.
                </div>

                <div class="alert alert-secondary small">
                    <strong>Important:</strong>
                    the backup is encrypted using the application's <code>APP_KEY</code>.
                    Keep that deployment key securely in your hosting configuration; it is not stored inside the backup file.
                </div>

                <a
                    href="{{ route('admin.backup.download') }}"
                    class="btn btn-primary"
                >
                    <i class="bi bi-download me-1"></i>
                    Download New Backup
                </a>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <strong>
                    <i class="bi bi-cloud-arrow-up me-1"></i>
                    Restore Backup
                </strong>
            </div>

            <div class="card-body">
                <p class="mb-3">
                    Upload a previously downloaded <code>.sjbackup</code> file. The system validates it first;
                    uploading alone does not modify the database.
                </p>

                <div class="alert alert-warning small">
                    Restore replaces the current application data. Only backups with the same database schema,
                    valid encryption, and your current active Barangay Captain account can continue.
                </div>

                <form
                    method="POST"
                    action="{{ route('admin.backup.validate') }}"
                    enctype="multipart/form-data"
                >
                    @csrf

                    <label
                        for="backup_file"
                        class="form-label"
                    >
                        Backup File
                    </label>

                    <input
                        id="backup_file"
                        type="file"
                        name="backup_file"
                        class="form-control mb-3"
                        accept=".sjbackup"
                        required
                    >

                    <button
                        type="submit"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-shield-check me-1"></i>
                        Validate Backup
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>Current Database Snapshot</strong>
        <span class="text-muted small">
            Counts shown before creating or restoring a backup
        </span>
    </div>

    <div class="card-body">
        <div class="row g-3">
            @foreach($labels as $table => $label)
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            {{ $label }}
                        </div>
                        <div class="fs-4 fw-semibold">
                            {{ number_format($counts[$table] ?? 0) }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
