@extends('layouts.app')

@section('title', 'Edit Resident')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">
            Edit Resident
        </h3>

        <div class="text-muted">
            {{ $resident->resident_code }} — {{ $resident->full_name }}
        </div>
    </div>

    <a
        href="{{ route('residents.show', $resident) }}"
        class="btn btn-outline-secondary"
    >
        Back
    </a>

</div>


@if($errors->any())

    <div class="alert alert-danger">

        <strong>
            Please correct the following:
        </strong>

        <ul class="mb-0 mt-2">

            @foreach($errors->all() as $error)
                <li>
                    {{ $error }}
                </li>
            @endforeach

        </ul>

    </div>

@endif


<form
    method="POST"
    action="{{ route('residents.update', $resident) }}"
>

    @csrf
    @method('PUT')


    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white">
            <strong>
                Personal Information
            </strong>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-3">
                    <label class="form-label">
                        First Name *
                    </label>

                    <input
                        type="text"
                        name="first_name"
                        value="{{ old('first_name', $resident->first_name) }}"
                        class="form-control"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Middle Name
                    </label>

                    <input
                        type="text"
                        name="middle_name"
                        value="{{ old('middle_name', $resident->middle_name) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Last Name *
                    </label>

                    <input
                        type="text"
                        name="last_name"
                        value="{{ old('last_name', $resident->last_name) }}"
                        class="form-control"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Suffix
                    </label>

                    <input
                        type="text"
                        name="suffix"
                        value="{{ old('suffix', $resident->suffix) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Sex
                    </label>

                    <select
                        name="sex"
                        class="form-select"
                    >
                        <option value="">
                            Select Sex
                        </option>

                        <option
                            value="Male"
                            @selected(old('sex', $resident->sex) === 'Male')
                        >
                            Male
                        </option>

                        <option
                            value="Female"
                            @selected(old('sex', $resident->sex) === 'Female')
                        >
                            Female
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Birth Date
                    </label>

                    <input
                        type="date"
                        name="birth_date"
                        value="{{
                            old(
                                'birth_date',
                                $resident->birth_date?->format('Y-m-d')
                            )
                        }}"
                        max="{{ now()->toDateString() }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Civil Status
                    </label>

                    <input
                        type="text"
                        name="civil_status"
                        value="{{ old('civil_status', $resident->civil_status) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Classification
                    </label>

                    <input
                        type="text"
                        name="classification"
                        value="{{ old('classification', $resident->classification) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Contact Number
                    </label>

                    <input
                        type="text"
                        name="contact_number"
                        value="{{ old('contact_number', $resident->contact_number) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $resident->email) }}"
                        class="form-control"
                    >
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
                    <label class="form-label">
                        House Number
                    </label>

                    <input
                        type="text"
                        name="house_number"
                        value="{{ old('house_number', $resident->house_number) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Street
                    </label>

                    <input
                        type="text"
                        name="street"
                        value="{{ old('street', $resident->street) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Purok / Sitio
                    </label>

                    <input
                        type="text"
                        name="purok"
                        value="{{ old('purok', $resident->purok) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Barangay
                    </label>

                    <input
                        type="text"
                        value="Barangay San Jose"
                        class="form-control"
                        disabled
                    >
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Additional Address Details
                    </label>

                    <textarea
                        name="address_details"
                        rows="3"
                        class="form-control"
                    >{{ old('address_details', $resident->address_details) }}</textarea>
                </div>

            </div>

        </div>

    </div>


    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white">
            <strong>
                Resident Information
            </strong>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">
                        Registered Voter
                    </label>

                    <select
                        name="is_registered_voter"
                        class="form-select"
                    >
                        <option
                            value="0"
                            @selected(
                                old(
                                    'is_registered_voter',
                                    $resident->is_registered_voter ? '1' : '0'
                                ) == '0'
                            )
                        >
                            No
                        </option>

                        <option
                            value="1"
                            @selected(
                                old(
                                    'is_registered_voter',
                                    $resident->is_registered_voter ? '1' : '0'
                                ) == '1'
                            )
                        >
                            Yes
                        </option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Vulnerable Sector
                    </label>

                    <input
                        type="text"
                        name="vulnerable_sector"
                        value="{{ old('vulnerable_sector', $resident->vulnerable_sector) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Record Status *
                    </label>

                    <select
                        name="is_active"
                        class="form-select"
                        required
                    >
                        <option
                            value="1"
                            @selected(
                                old(
                                    'is_active',
                                    $resident->is_active ? '1' : '0'
                                ) == '1'
                            )
                        >
                            Active
                        </option>

                        <option
                            value="0"
                            @selected(
                                old(
                                    'is_active',
                                    $resident->is_active ? '1' : '0'
                                ) == '0'
                            )
                        >
                            Inactive
                        </option>
                    </select>
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

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">
                        Emergency Contact Name
                    </label>

                    <input
                        type="text"
                        name="emergency_contact_name"
                        value="{{ old('emergency_contact_name', $resident->emergency_contact_name) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Emergency Contact Number
                    </label>

                    <input
                        type="text"
                        name="emergency_contact_number"
                        value="{{ old('emergency_contact_number', $resident->emergency_contact_number) }}"
                        class="form-control"
                    >
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

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">
                        Government ID Type
                    </label>

                    <input
                        type="text"
                        name="government_id_type"
                        value="{{ old('government_id_type', $resident->government_id_type) }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Government ID Number
                    </label>

                    <input
                        type="text"
                        name="government_id_number"
                        value="{{ old('government_id_number', $resident->government_id_number) }}"
                        class="form-control"
                    >
                </div>

            </div>

        </div>

    </div>


    <div class="d-flex gap-2 mb-5">

        <button
            type="submit"
            class="btn btn-primary"
        >
            Save Changes
        </button>

        <a
            href="{{ route('residents.show', $resident) }}"
            class="btn btn-outline-secondary"
        >
            Cancel
        </a>

    </div>

</form>

@endsection
