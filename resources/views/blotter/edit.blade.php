@extends('layouts.app')

@section('title', 'Edit Case - ' . $case->reference_number)
@section('page-title', 'Case Management')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">
            Edit Case
        </h3>

        <div class="text-muted">
            {{ $case->reference_number }}
        </div>
    </div>

    <a
        href="{{ route('cases.show', $case) }}"
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


<div class="card shadow-sm">

    <div class="card-header bg-white">

        <strong>
            Case Information
        </strong>

    </div>

    <div class="card-body">

        <form
            action="{{ route('cases.update', $case) }}"
            method="POST"
        >

            @csrf
            @method('PUT')


            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label">
                        Reference Number
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="{{ $case->reference_number }}"
                        disabled
                    >

                    <div class="form-text">
                        Reference numbers cannot be changed.
                    </div>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        Current Stage / Case Status
                    </label>

                    <div class="border rounded p-3 bg-light">

                        <div class="d-flex flex-wrap gap-2">

                            <span
                                class="badge {{
                                    $case->case_stage?->badgeClass()
                                    ?? 'text-bg-secondary'
                                }}"
                            >
                                {{
                                    $case->case_stage?->value
                                    ?? 'New'
                                }}
                            </span>

                            <span
                                class="badge {{
                                    $case->record_status?->badgeClass()
                                    ?? 'text-bg-primary'
                                }}"
                            >
                                {{
                                    $case->record_status?->value
                                    ?? 'Open'
                                }}
                            </span>

                        </div>

                        <div class="form-text mt-2">
                            Workflow stage and case status are changed through
                            Case Management actions, not through this edit form.
                        </div>

                    </div>

                </div>


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
                                    old(
                                        'incident_type_id',
                                        $case->incident_type_id
                                    ) == $type->id
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
                            $case->incident_date->format('Y-m-d')
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
                        value="{{ old(
                            'incident_time',
                            $case->incident_time
                                ? substr($case->incident_time, 0, 5)
                                : ''
                        ) }}"
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
                        value="{{ old(
                            'location',
                            $case->location
                        ) }}"
                        class="form-control"
                        required
                    >

                </div>


                <div class="col-md-12">

                    <label class="form-label">
                        Narrative / Complaint *
                    </label>

                    <textarea
                        name="narrative"
                        class="form-control"
                        rows="6"
                        required
                    >{{ old(
                        'narrative',
                        $case->narrative
                    ) }}</textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        Initial Action
                    </label>

                    <textarea
                        name="initial_action"
                        class="form-control"
                        rows="4"
                    >{{ old(
                        'initial_action',
                        $case->initial_action
                    ) }}</textarea>

                </div>


                <div class="col-md-6">

                    <label class="form-label">
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        class="form-control"
                        rows="4"
                    >{{ old(
                        'remarks',
                        $case->remarks
                    ) }}</textarea>

                </div>

            </div>


            <hr class="my-4">


            <div class="d-flex justify-content-between">

                <div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Changes
                    </button>

                    <a
                        href="{{ route('cases.show', $case) }}"
                        class="btn btn-light"
                    >
                        Cancel
                    </a>

                </div>

            </div>

        </form>

    </div>

</div>

@endsection