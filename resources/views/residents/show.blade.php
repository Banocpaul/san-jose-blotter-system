@extends('layouts.app')

@section('title', 'Complainant Details')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>
        <h3 class="mb-1">
            {{ $resident->full_name }}
        </h3>

        <div class="text-muted">
            {{ $resident->resident_code }}
        </div>
    </div>

    <div class="d-flex gap-2">

        <a
            href="{{ route('residents.index') }}"
            class="btn btn-outline-secondary"
        >
            Back
        </a>

        <a
            href="{{ route('residents.edit', $resident) }}"
            class="btn btn-primary"
        >
            Edit Resident
        </a>

    </div>

</div>


@if(session('success'))

    <div class="alert alert-success alert-dismissible fade show">

        {{ session('success') }}

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

@endif


<div class="row g-4">

    <div class="col-lg-8">

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Personal Information
                </strong>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-6">
                        <div class="text-muted small">
                            Full Name
                        </div>

                        <strong>
                            {{ $resident->full_name }}
                        </strong>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">
                            Sex
                        </div>

                        <div>
                            {{ $resident->sex ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">
                            Birth Date
                        </div>

                        <div>
                            {{
                                $resident->birth_date
                                    ?->format('F d, Y')
                                ?? '—'
                            }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">
                            Civil Status
                        </div>

                        <div>
                            {{ $resident->civil_status ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">
                            Classification
                        </div>

                        <div>
                            {{ $resident->classification ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">
                            Vulnerable Sector
                        </div>

                        <div>
                            {{ $resident->vulnerable_sector ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">
                            Contact Number
                        </div>

                        <div>
                            {{ $resident->contact_number ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">
                            Email
                        </div>

                        <div>
                            {{ $resident->email ?? '—' }}
                        </div>
                    </div>

                </div>

            </div>

        </div>


        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Address
                </strong>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-3">
                        <div class="text-muted small">
                            House Number
                        </div>

                        <div>
                            {{ $resident->house_number ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">
                            Street
                        </div>

                        <div>
                            {{ $resident->street ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">
                            Purok / Sitio
                        </div>

                        <div>
                            {{ $resident->purok ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">
                            Barangay
                        </div>

                        <div>
                            Barangay San Jose
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="text-muted small">
                            Additional Address Details
                        </div>

                        <div>
                            {{ $resident->address_details ?? '—' }}
                        </div>
                    </div>

                </div>

            </div>

        </div>


        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Case Participation
                </strong>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <div class="text-muted small">
                                Complaints
                            </div>

                            <div class="fs-4 fw-semibold">
                                {{ $resident->complaints->count() }}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <div class="text-muted small">
                                Respondent Records
                            </div>

                            <div class="fs-4 fw-semibold">
                                {{ $resident->responses->count() }}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <div class="text-muted small">
                                Witness Records
                            </div>

                            <div class="fs-4 fw-semibold">
                                {{ $resident->witnessRecords->count() }}
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-lg-4">

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Resident Record
                </strong>
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <div class="text-muted small">
                        Complainant Code
                    </div>

                    <strong>
                        {{ $resident->resident_code }}
                    </strong>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">
                        Record Status
                    </div>

                    @if($resident->is_active)

                        <span class="badge text-bg-success">
                            Active
                        </span>

                    @else

                        <span class="badge text-bg-warning">
                            Inactive
                        </span>

                    @endif
                </div>

                <div class="mb-3">
                    <div class="text-muted small">
                        Registered Voter
                    </div>

                    <div>
                        {{ $resident->is_registered_voter ? 'Yes' : 'No' }}
                    </div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">
                        Created By
                    </div>

                    <div>
                        {{ $resident->creator?->name ?? '—' }}
                    </div>
                </div>

            </div>

        </div>


        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Emergency Contact
                </strong>
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <div class="text-muted small">
                        Name
                    </div>

                    <div>
                        {{ $resident->emergency_contact_name ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="text-muted small">
                        Contact Number
                    </div>

                    <div>
                        {{ $resident->emergency_contact_number ?? '—' }}
                    </div>
                </div>

            </div>

        </div>


        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Government Identification
                </strong>
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <div class="text-muted small">
                        ID Type
                    </div>

                    <div>
                        {{ $resident->government_id_type ?? '—' }}
                    </div>
                </div>

                <div>
                    <div class="text-muted small">
                        ID Number
                    </div>

                    <div>
                        {{ $resident->government_id_number ?? '—' }}
                    </div>
                </div>

            </div>

        </div>


        <div class="card shadow-sm">

            <div class="card-header bg-white">
                <strong>
                    Archive Resident
                </strong>
            </div>

            <div class="card-body">

                <form
                    method="POST"
                    action="{{ route('residents.destroy', $resident) }}"
                    onsubmit="return confirm('Archive this resident?');"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="btn btn-outline-danger w-100"
                    >
                        Archive Resident
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

@endsection
