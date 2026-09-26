@extends('layouts.app')

@section('title', 'New Blotter Case')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">New Blotter Case</h3>
        <div class="text-muted">
            Select the parties from the People Directory, then record the incident details.
        </div>
    </div>

    <a href="{{ route('blotter.index') }}" class="btn btn-outline-secondary">
        Back
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('blotter.store') }}">
    @csrf

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong>People Involved</strong>
        </div>

        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-6">
                    @include('blotter._person-picker', [
                        'prefix' => 'complainant',
                        'label' => 'Complainant',
                    ])
                </div>

                <div class="col-lg-6">
                    @include('blotter._person-picker', [
                        'prefix' => 'respondent',
                        'label' => 'Respondent',
                    ])
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong>Incident Information</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Incident Type *</label>
                    <select
                        name="incident_type_id"
                        class="form-select @error('incident_type_id') is-invalid @enderror"
                        required
                    >
                        <option value="">Select Incident Type</option>
                        @foreach($incidentTypes as $type)
                            <option
                                value="{{ $type->id }}"
                                @selected(old('incident_type_id') == $type->id)
                            >
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('incident_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Incident Date *</label>
                    <input
                        type="date"
                        name="incident_date"
                        value="{{ old('incident_date', now()->toDateString()) }}"
                        class="form-control @error('incident_date') is-invalid @enderror"
                        required
                    >
                    @error('incident_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Incident Time</label>
                    <input
                        type="time"
                        name="incident_time"
                        value="{{ old('incident_time') }}"
                        class="form-control @error('incident_time') is-invalid @enderror"
                    >
                    @error('incident_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Location *</label>
                    <input
                        type="text"
                        name="location"
                        value="{{ old('location') }}"
                        class="form-control @error('location') is-invalid @enderror"
                        maxlength="255"
                        required
                    >
                    @error('location')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Complaint / Narrative *</label>
                    <textarea
                        name="narrative"
                        rows="5"
                        class="form-control @error('narrative') is-invalid @enderror"
                        required
                    >{{ old('narrative') }}</textarea>
                    @error('narrative')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Initial Action</label>
                    <textarea
                        name="initial_action"
                        rows="3"
                        class="form-control @error('initial_action') is-invalid @enderror"
                    >{{ old('initial_action') }}</textarea>
                    @error('initial_action')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Remarks</label>
                    <textarea
                        name="remarks"
                        rows="3"
                        class="form-control @error('remarks') is-invalid @enderror"
                    >{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary">
            Save Blotter Case
        </button>

        <a href="{{ route('blotter.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>

@endsection
