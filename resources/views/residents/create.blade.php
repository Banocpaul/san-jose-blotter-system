@extends('layouts.app')

@section('title', 'New Blotter Case')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">
            New Blotter Case
        </h3>

        <div class="text-muted">
            Record a new Barangay San Jose complaint.
        </div>
    </div>

    <a
        href="{{ route('blotter.index') }}"
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
                <li>{{ $error }}</li>
            @endforeach
        </ul>

    </div>

@endif

<form
    method="POST"
    action="{{ route('blotter.store') }}"
>

    @csrf

    {{-- INCIDENT --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white">
            <strong>Incident Information</strong>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label">
                        Incident Type *
                    </label>

                    <select
                        name="incident_type_id"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Select Incident Type
                        </option>

                        @foreach($incidentTypes as $type)

                            <option
                                value="{{ $type->id }}"
                                @selected(
                                    old('incident_type_id')
                                    == $type->id
                                )
                            >
                                {{ $type->name }}
                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Incident Date *
                    </label>

                    <input
                        type="date"
                        name="incident_date"
                        value="{{ old(
                            'incident_date',
                            now()->toDateString()
                        ) }}"
                        class="form-control"
                        required
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Incident Time
                    </label>

                    <input
                        type="time"
                        name="incident_time"
                        value="{{ old('incident_time') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-12">

                    <label class="form-label">
                        Location *
                    </label>

                    <input
                        type="text"
                        name="location"
                        value="{{ old('location') }}"
                        class="form-control"
                        required
                    >

                </div>

                <div class="col-md-12">

                    <label class="form-label">
                        Complaint / Narrative *
                    </label>

                    <textarea
                        name="narrative"
                        rows="5"
                        class="form-control"
                        required
                    >{{ old('narrative') }}</textarea>

                </div>

                <div class="col-md-6">

                    <label class="form-label">
                        Initial Action
                    </label>

                    <textarea
                        name="initial_action"
                        rows="3"
                        class="form-control"
                    >{{ old('initial_action') }}</textarea>

                </div>

                <div class="col-md-6">

                    <label class="form-label">
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        rows="3"
                        class="form-control"
                    >{{ old('remarks') }}</textarea>

                </div>

            </div>

        </div>

    </div>


    {{-- COMPLAINANT --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white">
            <div>
                <strong>Complainant</strong>
                <div class="small text-muted mt-1">
                    Returning complainants can be selected from the saved directory.
                </div>
            </div>
        </div>

        <div class="card-body">

            <div class="mb-4">

                <label class="form-label">
                    Existing Complainant Record
                </label>

                <select
                    name="complainant_resident_id"
                    class="form-select"
                >

                    <option value="">
                        New person / Enter manually below
                    </option>

                    @foreach($residents as $resident)

                        <option
                            value="{{ $resident->id }}"
                            @selected(
                                old('complainant_resident_id')
                                == $resident->id
                            )
                        >
                            {{ $resident->resident_code }}
                            —
                            {{ $resident->full_name }}
                        </option>

                    @endforeach

                </select>

                <div class="form-text">
                    If this person has filed a complaint before, select their saved record here. Otherwise enter the information below; it will be saved automatically to Complainant Records.
                </div>

            </div>


            <div class="row g-3">

                <div class="col-md-3">

                    <label class="form-label">
                        First Name
                    </label>

                    <input
                        type="text"
                        name="complainant_first_name"
                        value="{{ old('complainant_first_name') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Middle Name
                    </label>

                    <input
                        type="text"
                        name="complainant_middle_name"
                        value="{{ old('complainant_middle_name') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Last Name
                    </label>

                    <input
                        type="text"
                        name="complainant_last_name"
                        value="{{ old('complainant_last_name') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Suffix
                    </label>

                    <input
                        type="text"
                        name="complainant_suffix"
                        value="{{ old('complainant_suffix') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-6">

                    <label class="form-label">
                        Contact Number
                    </label>

                    <input
                        type="text"
                        name="complainant_contact_number"
                        value="{{ old('complainant_contact_number') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-12">

                    <div class="form-check mt-1">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            value="1"
                            id="complainant_not_san_jose"
                            name="complainant_not_san_jose"
                            @checked(old('complainant_not_san_jose'))
                        >

                        <label
                            class="form-check-label"
                            for="complainant_not_san_jose"
                        >
                            Not from Barangay San Jose
                        </label>

                    </div>

                    <div class="form-text">
                        Leave this unchecked for a Barangay San Jose address.
                    </div>

                </div>


                <div
                    id="complainant_san_jose_address_fields"
                    class="col-12"
                >

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                House Number *
                            </label>

                            <input
                                type="text"
                                id="complainant_house_number"
                                name="complainant_house_number"
                                value="{{ old('complainant_house_number') }}"
                                class="form-control @error('complainant_house_number') is-invalid @enderror"
                                maxlength="50"
                            >

                            @error('complainant_house_number')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Sitio *
                            </label>

                            <select
                                id="complainant_sitio"
                                name="complainant_sitio"
                                class="form-select @error('complainant_sitio') is-invalid @enderror"
                            >

                                <option value="">
                                    Select Sitio
                                </option>

                                @foreach([
                                    'Sitio 1',
                                    'Sitio 2',
                                    'Sitio 3',
                                    'Sitio 4'
                                ] as $sitio)

                                    <option
                                        value="{{ $sitio }}"
                                        @selected(
                                            old('complainant_sitio')
                                            === $sitio
                                        )
                                    >
                                        {{ $sitio }}
                                    </option>

                                @endforeach

                            </select>

                            @error('complainant_sitio')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>

                    </div>

                </div>


                <div
                    id="complainant_outside_address_field"
                    class="col-12 d-none"
                >

                    <label class="form-label">
                        Complete Address *
                    </label>

                    <textarea
                        id="complainant_address"
                        name="complainant_address"
                        rows="3"
                        class="form-control @error('complainant_address') is-invalid @enderror"
                        placeholder="Enter the complete address outside Barangay San Jose"
                    >{{ old('complainant_address') }}</textarea>

                    @error('complainant_address')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>


                <div
                    id="complainant_registered_address_note"
                    class="col-12 d-none"
                >

                    <div class="alert alert-light border mb-0">
                        The address saved in the selected complainant record
                        will be used automatically.
                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- RESPONDENT --}}
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white">
            <strong>Respondent</strong>
        </div>

        <div class="card-body">

            <div class="mb-4">

                <label class="form-label">
                    Existing Complainant Record
                </label>

                <select
                    name="respondent_resident_id"
                    class="form-select"
                >

                    <option value="">
                        New person / Enter manually below
                    </option>

                    @foreach($residents as $resident)

                        <option
                            value="{{ $resident->id }}"
                            @selected(
                                old('respondent_resident_id')
                                == $resident->id
                            )
                        >
                            {{ $resident->resident_code }}
                            —
                            {{ $resident->full_name }}
                        </option>

                    @endforeach

                </select>

                <div class="form-text">
                    If the respondent already exists in Complainant Records, you may reuse that profile. Otherwise enter the respondent manually; respondents are not added to Complainant Records automatically.
                </div>

            </div>


            <div class="row g-3">

                <div class="col-md-3">

                    <label class="form-label">
                        First Name
                    </label>

                    <input
                        type="text"
                        name="respondent_first_name"
                        value="{{ old('respondent_first_name') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Middle Name
                    </label>

                    <input
                        type="text"
                        name="respondent_middle_name"
                        value="{{ old('respondent_middle_name') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Last Name
                    </label>

                    <input
                        type="text"
                        name="respondent_last_name"
                        value="{{ old('respondent_last_name') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-3">

                    <label class="form-label">
                        Suffix
                    </label>

                    <input
                        type="text"
                        name="respondent_suffix"
                        value="{{ old('respondent_suffix') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-md-6">

                    <label class="form-label">
                        Contact Number
                    </label>

                    <input
                        type="text"
                        name="respondent_contact_number"
                        value="{{ old('respondent_contact_number') }}"
                        class="form-control"
                    >

                </div>

                <div class="col-12">

                    <div class="form-check mt-1">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            value="1"
                            id="respondent_not_san_jose"
                            name="respondent_not_san_jose"
                            @checked(old('respondent_not_san_jose'))
                        >

                        <label
                            class="form-check-label"
                            for="respondent_not_san_jose"
                        >
                            Not from Barangay San Jose
                        </label>

                    </div>

                    <div class="form-text">
                        Leave this unchecked for a Barangay San Jose address.
                    </div>

                </div>


                <div
                    id="respondent_san_jose_address_fields"
                    class="col-12"
                >

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                House Number *
                            </label>

                            <input
                                type="text"
                                id="respondent_house_number"
                                name="respondent_house_number"
                                value="{{ old('respondent_house_number') }}"
                                class="form-control @error('respondent_house_number') is-invalid @enderror"
                                maxlength="50"
                            >

                            @error('respondent_house_number')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Sitio *
                            </label>

                            <select
                                id="respondent_sitio"
                                name="respondent_sitio"
                                class="form-select @error('respondent_sitio') is-invalid @enderror"
                            >

                                <option value="">
                                    Select Sitio
                                </option>

                                @foreach([
                                    'Sitio 1',
                                    'Sitio 2',
                                    'Sitio 3',
                                    'Sitio 4'
                                ] as $sitio)

                                    <option
                                        value="{{ $sitio }}"
                                        @selected(
                                            old('respondent_sitio')
                                            === $sitio
                                        )
                                    >
                                        {{ $sitio }}
                                    </option>

                                @endforeach

                            </select>

                            @error('respondent_sitio')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>

                    </div>

                </div>


                <div
                    id="respondent_outside_address_field"
                    class="col-12 d-none"
                >

                    <label class="form-label">
                        Complete Address *
                    </label>

                    <textarea
                        id="respondent_address"
                        name="respondent_address"
                        rows="3"
                        class="form-control @error('respondent_address') is-invalid @enderror"
                        placeholder="Enter the complete address outside Barangay San Jose"
                    >{{ old('respondent_address') }}</textarea>

                    @error('respondent_address')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>


                <div
                    id="respondent_registered_address_note"
                    class="col-12 d-none"
                >

                    <div class="alert alert-light border mb-0">
                        The address saved in the selected complainant record
                        will be used automatically.
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="d-flex gap-2 mb-5">

        <button
            type="submit"
            class="btn btn-primary"
        >
            Save Blotter Case
        </button>

        <a
            href="{{ route('blotter.index') }}"
            class="btn btn-outline-secondary"
        >
            Cancel
        </a>

    </div>

</form>


@push('scripts')

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        function setupPartyAddress(prefix) {

            const residentSelect =
                document.querySelector(
                    `[name="${prefix}_resident_id"]`
                );

            const outsideCheckbox =
                document.getElementById(
                    `${prefix}_not_san_jose`
                );

            const sanJoseFields =
                document.getElementById(
                    `${prefix}_san_jose_address_fields`
                );

            const outsideField =
                document.getElementById(
                    `${prefix}_outside_address_field`
                );

            const registeredNote =
                document.getElementById(
                    `${prefix}_registered_address_note`
                );

            const houseNumber =
                document.getElementById(
                    `${prefix}_house_number`
                );

            const sitio =
                document.getElementById(
                    `${prefix}_sitio`
                );

            const completeAddress =
                document.getElementById(
                    `${prefix}_address`
                );


            function syncAddressFields() {

                const hasRegisteredResident =
                    residentSelect
                    &&
                    residentSelect.value !== '';

                /*
                |--------------------------------------------------------------------------
                | Existing Complainant Record Selected
                |--------------------------------------------------------------------------
                |
                | The resident record is the source of the address.
                |
                */

                if (hasRegisteredResident) {

                    outsideCheckbox.checked =
                        false;

                    outsideCheckbox.disabled =
                        true;

                    sanJoseFields.classList.add(
                        'd-none'
                    );

                    outsideField.classList.add(
                        'd-none'
                    );

                    registeredNote.classList.remove(
                        'd-none'
                    );

                    houseNumber.disabled =
                        true;

                    sitio.disabled =
                        true;

                    completeAddress.disabled =
                        true;

                    houseNumber.required =
                        false;

                    sitio.required =
                        false;

                    completeAddress.required =
                        false;

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Manual Entry
                |--------------------------------------------------------------------------
                */

                outsideCheckbox.disabled =
                    false;

                registeredNote.classList.add(
                    'd-none'
                );


                /*
                |--------------------------------------------------------------------------
                | Non-San Jose Address
                |--------------------------------------------------------------------------
                */

                if (outsideCheckbox.checked) {

                    sanJoseFields.classList.add(
                        'd-none'
                    );

                    outsideField.classList.remove(
                        'd-none'
                    );

                    houseNumber.disabled =
                        true;

                    sitio.disabled =
                        true;

                    completeAddress.disabled =
                        false;

                    houseNumber.required =
                        false;

                    sitio.required =
                        false;

                    completeAddress.required =
                        true;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Barangay San Jose Address
                    |--------------------------------------------------------------------------
                    */

                    sanJoseFields.classList.remove(
                        'd-none'
                    );

                    outsideField.classList.add(
                        'd-none'
                    );

                    houseNumber.disabled =
                        false;

                    sitio.disabled =
                        false;

                    completeAddress.disabled =
                        true;

                    houseNumber.required =
                        true;

                    sitio.required =
                        true;

                    completeAddress.required =
                        false;
                }
            }


            residentSelect?.addEventListener(
                'change',
                syncAddressFields
            );


            outsideCheckbox?.addEventListener(
                'change',
                syncAddressFields
            );


            syncAddressFields();
        }


        setupPartyAddress(
            'complainant'
        );

        setupPartyAddress(
            'respondent'
        );

    }
);
</script>

@endpush


@endsection