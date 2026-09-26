@php
    $person = $resident ?? null;
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Personal Information</strong>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">First Name *</label>
                <input
                    type="text"
                    name="first_name"
                    value="{{ old('first_name', $person?->first_name) }}"
                    class="form-control @error('first_name') is-invalid @enderror"
                    required
                >
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Middle Name</label>
                <input
                    type="text"
                    name="middle_name"
                    value="{{ old('middle_name', $person?->middle_name) }}"
                    class="form-control @error('middle_name') is-invalid @enderror"
                >
                @error('middle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Last Name *</label>
                <input
                    type="text"
                    name="last_name"
                    value="{{ old('last_name', $person?->last_name) }}"
                    class="form-control @error('last_name') is-invalid @enderror"
                    required
                >
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Suffix</label>
                <input
                    type="text"
                    name="suffix"
                    value="{{ old('suffix', $person?->suffix) }}"
                    class="form-control @error('suffix') is-invalid @enderror"
                >
                @error('suffix')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Classification *</label>
                <select
                    name="classification"
                    class="form-select @error('classification') is-invalid @enderror"
                    required
                >
                    <option value="">Select classification</option>
                    @foreach(['Resident', 'Non-Resident'] as $classificationOption)
                        <option
                            value="{{ $classificationOption }}"
                            @selected(old('classification', $person?->classification) === $classificationOption)
                        >
                            {{ $classificationOption }}
                        </option>
                    @endforeach
                </select>
                @error('classification')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Sex</label>
                <select name="sex" class="form-select @error('sex') is-invalid @enderror">
                    <option value="">Select sex</option>
                    @foreach(['Male', 'Female'] as $sexOption)
                        <option
                            value="{{ $sexOption }}"
                            @selected(old('sex', $person?->sex) === $sexOption)
                        >
                            {{ $sexOption }}
                        </option>
                    @endforeach
                </select>
                @error('sex')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Birth Date</label>
                <input
                    type="date"
                    name="birth_date"
                    value="{{ old('birth_date', $person?->birth_date?->format('Y-m-d')) }}"
                    max="{{ now()->toDateString() }}"
                    class="form-control @error('birth_date') is-invalid @enderror"
                >
                @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Civil Status</label>
                <input
                    type="text"
                    name="civil_status"
                    value="{{ old('civil_status', $person?->civil_status) }}"
                    class="form-control @error('civil_status') is-invalid @enderror"
                >
                @error('civil_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Contact & Address</strong>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input
                    type="text"
                    name="contact_number"
                    value="{{ old('contact_number', $person?->contact_number) }}"
                    class="form-control @error('contact_number') is-invalid @enderror"
                >
                @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $person?->email) }}"
                    class="form-control @error('email') is-invalid @enderror"
                >
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">House Number</label>
                <input
                    type="text"
                    name="house_number"
                    value="{{ old('house_number', $person?->house_number) }}"
                    class="form-control @error('house_number') is-invalid @enderror"
                >
                @error('house_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Street</label>
                <input
                    type="text"
                    name="street"
                    value="{{ old('street', $person?->street) }}"
                    class="form-control @error('street') is-invalid @enderror"
                >
                @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-5">
                <label class="form-label">Purok / Sitio</label>
                <input
                    type="text"
                    name="purok"
                    value="{{ old('purok', $person?->purok) }}"
                    class="form-control @error('purok') is-invalid @enderror"
                >
                @error('purok')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Complete / Additional Address Details</label>
                <textarea
                    name="address_details"
                    rows="3"
                    class="form-control @error('address_details') is-invalid @enderror"
                    placeholder="For non-residents, enter the complete outside address here."
                >{{ old('address_details', $person?->address_details) }}</textarea>
                @error('address_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Additional Information</strong>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Registered Voter</label>
                <select name="is_registered_voter" class="form-select">
                    <option value="0" @selected(old('is_registered_voter', $person?->is_registered_voter ? '1' : '0') == '0')>No</option>
                    <option value="1" @selected(old('is_registered_voter', $person?->is_registered_voter ? '1' : '0') == '1')>Yes</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Vulnerable Sector</label>
                <input
                    type="text"
                    name="vulnerable_sector"
                    value="{{ old('vulnerable_sector', $person?->vulnerable_sector) }}"
                    class="form-control @error('vulnerable_sector') is-invalid @enderror"
                >
                @error('vulnerable_sector')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Record Status *</label>
                <select name="is_active" class="form-select" required>
                    <option value="1" @selected(old('is_active', $person?->is_active ?? true) == true)>Active</option>
                    <option value="0" @selected(old('is_active', $person?->is_active ?? true) == false)>Inactive</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Emergency Contact</strong>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Emergency Contact Name</label>
                <input
                    type="text"
                    name="emergency_contact_name"
                    value="{{ old('emergency_contact_name', $person?->emergency_contact_name) }}"
                    class="form-control @error('emergency_contact_name') is-invalid @enderror"
                >
                @error('emergency_contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Emergency Contact Number</label>
                <input
                    type="text"
                    name="emergency_contact_number"
                    value="{{ old('emergency_contact_number', $person?->emergency_contact_number) }}"
                    class="form-control @error('emergency_contact_number') is-invalid @enderror"
                >
                @error('emergency_contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Government Identification</strong>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Government ID Type</label>
                <input
                    type="text"
                    name="government_id_type"
                    value="{{ old('government_id_type', $person?->government_id_type) }}"
                    class="form-control @error('government_id_type') is-invalid @enderror"
                >
                @error('government_id_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Government ID Number</label>
                <input
                    type="text"
                    name="government_id_number"
                    value="{{ old('government_id_number', $person?->government_id_number) }}"
                    class="form-control @error('government_id_number') is-invalid @enderror"
                >
                @error('government_id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
