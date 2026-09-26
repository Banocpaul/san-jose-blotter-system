@extends('layouts.app')

@section('title', 'Blotter Case Details')

@section('content')

@php
    $caseStatus = $case->status instanceof \App\Enums\CaseStatus
        ? $case->status->value
        : $case->status;

    $caseStage = $case->case_stage instanceof \App\Enums\CaseStage
        ? $case->case_stage->value
        : ($case->case_stage ?? 'New');

    $currentProceedingLabel = $caseStage === 'For Pangkat/Conciliation'
        ? 'Pangkat Conciliation'
        : 'Mediation';

    $statusClass = match($caseStatus) {
        'Pending' => 'text-bg-warning',
        'Under Investigation' => 'text-bg-primary',
        'For Mediation' => 'text-bg-info',
        'Settled' => 'text-bg-success',
        'Resolved' => 'text-bg-success',
        'Referred' => 'text-bg-dark',
        'Dismissed' => 'text-bg-secondary',
        default => 'text-bg-secondary',
    };

    $hasActiveScheduledHearing = $case
        ->mediationSessions
        ->contains(function ($session) {
            return $session->status === 'Scheduled';
        });

    $closedStatuses = [
        'Settled',
        'Resolved',
        'Referred',
        'Dismissed',
    ];
@endphp


{{-- ========================================================= --}}
{{-- SUCCESS MESSAGE --}}
{{-- ========================================================= --}}

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


{{-- ========================================================= --}}
{{-- VALIDATION ERRORS --}}
{{-- ========================================================= --}}

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


{{-- ========================================================= --}}
{{-- PAGE HEADER --}}
{{-- ========================================================= --}}

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <h3 class="mb-1">
            {{ $case->reference_number }}
        </h3>

        <span class="badge {{ $statusClass }}">
            {{ $caseStatus }}
        </span>

        <span class="badge text-bg-light border text-dark">
            Stage: {{ $caseStage }}
        </span>

    </div>


    <div class="d-flex gap-2">

        <a
            href="{{ request()->routeIs('blotter.show') && url()->previous() !== url()->current() ? url()->previous() : route('blotter.index') }}"
            class="btn btn-outline-secondary"
        >
            Back
        </a>

        @can('update', $case)
            <a
                href="{{ route('blotter.edit', $case) }}"
                class="btn btn-primary"
            >
                Edit Case
            </a>
        @endcan

        @can('delete', $case)
            <form
                method="POST"
                action="{{ route('blotter.destroy', $case) }}"
                onsubmit="return confirm('Archive this blotter case?');"
            >

                @csrf
                @method('DELETE')

                <button
                    type="submit"
                    class="btn btn-outline-danger"
                >
                    Archive
                </button>

            </form>
        @endcan

    </div>

</div>


<div class="row g-4">


    {{-- ========================================================= --}}
    {{-- LEFT COLUMN --}}
    {{-- ========================================================= --}}

    <div class="col-lg-8">


        {{-- ========================================================= --}}
        {{-- INCIDENT INFORMATION --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Incident Information
                </strong>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-6">

                        <div class="text-muted small">
                            Incident Type
                        </div>

                        <strong>
                            {{ $case->incidentType?->name ?? '—' }}
                        </strong>

                    </div>


                    <div class="col-md-3">

                        <div class="text-muted small">
                            Incident Date
                        </div>

                        <strong>

                            {{
                                $case->incident_date
                                    ?->format('F d, Y')
                                ?? '—'
                            }}

                        </strong>

                    </div>


                    <div class="col-md-3">

                        <div class="text-muted small">
                            Incident Time
                        </div>

                        <strong>

                            @if($case->incident_time)

                                {{
                                    date(
                                        'h:i A',
                                        strtotime($case->incident_time)
                                    )
                                }}

                            @else

                                —

                            @endif

                        </strong>

                    </div>


                    <div class="col-12">

                        <div class="text-muted small">
                            Location
                        </div>

                        <strong>
                            {{ $case->location }}
                        </strong>

                    </div>

                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- NARRATIVE --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Complaint / Incident Narrative
                </strong>
            </div>

            <div class="card-body">

                <div style="white-space: pre-line;">
                    {{ $case->narrative }}
                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- INITIAL ACTION --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Initial Action
                </strong>
            </div>

            <div class="card-body">

                @if($case->initial_action)

                    <div style="white-space: pre-line;">
                        {{ $case->initial_action }}
                    </div>

                @else

                    <span class="text-muted">
                        No initial action recorded.
                    </span>

                @endif

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- INVESTIGATION --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white d-flex justify-content-between align-items-center">

                <strong>
                    Investigation Notes
                </strong>

                <span class="badge text-bg-secondary">

                    {{
                        $case
                            ->investigationNotes
                            ->count()
                    }}

                </span>

            </div>


            <div class="card-body">


                @if(!in_array($caseStatus, $closedStatuses, true))

                    <h6 class="mb-3">
                        Add Investigation Note
                    </h6>


                    <form
                        method="POST"
                        action="{{ route(
                            'blotter.investigation-notes.store',
                            $case
                        ) }}"
                    >

                        @csrf


                        <div class="mb-3">

                            <label class="form-label">
                                Investigation Note *
                            </label>

                            <textarea
                                name="note"
                                class="form-control"
                                rows="4"
                                required
                            >{{ old('note') }}</textarea>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Action Taken
                            </label>

                            <textarea
                                name="action_taken"
                                class="form-control"
                                rows="3"
                            >{{ old('action_taken') }}</textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Add Investigation Note
                        </button>

                    </form>


                    <hr class="my-4">

                @endif


                <h6>
                    Investigation History
                </h6>


                @forelse(
                    $case
                        ->investigationNotes
                        ->sortByDesc('noted_at')
                    as $note
                )

                    <div class="border rounded p-3 mt-3">

                        <div class="d-flex justify-content-between gap-3 mb-2">

                            <div>

                                <strong>
                                    {{
                                        $note
                                            ->author
                                            ?->name
                                        ?? 'Unknown User'
                                    }}
                                </strong>


                                @if($note->author?->role)

                                    <div class="small text-muted">

                                        {{
                                            $note
                                                ->author
                                                ->role
                                                ->name
                                        }}

                                    </div>

                                @endif

                            </div>


                            <div class="small text-muted text-end">

                                {{
                                    $note
                                        ->noted_at
                                        ?->format(
                                            'M d, Y h:i A'
                                        )
                                }}

                            </div>

                        </div>


                        <div style="white-space: pre-line;">
                            {{ $note->note }}
                        </div>


                        @if($note->action_taken)

                            <div class="mt-3">

                                <div class="text-muted small">
                                    Action Taken
                                </div>

                                <div style="white-space: pre-line;">
                                    {{ $note->action_taken }}
                                </div>

                            </div>

                        @endif

                    </div>

                @empty

                    <div class="text-muted mt-3">
                        No investigation notes recorded yet.
                    </div>

                @endforelse

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- MEDIATION MANAGEMENT --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white d-flex justify-content-between align-items-center">

                <strong>
                    Lupon & Mediation Management
                </strong>

                <span class="badge text-bg-secondary">

                    {{
                        $case
                            ->mediationSessions
                            ->count()
                    }}

                    Proceeding(s)

                </span>

            </div>


            <div class="card-body">


                {{-- ================================================= --}}
                {{-- REFER CASE --}}
                {{-- ================================================= --}}

                @if(
                    in_array(
                        $caseStatus,
                        [
                            'Pending',
                            'Under Investigation'
                        ],
                        true
                    )
                )

                    <div class="alert alert-info">

                        <strong>
                            Mediation Available
                        </strong>

                        <div class="small mt-1">
                            This case may be referred to the Lupon for mediation.
                        </div>

                    </div>


                    <form
                        method="POST"
                        action="{{ route(
                            'blotter.mediation.refer',
                            $case
                        ) }}"
                        onsubmit="return confirm('Refer this case to mediation?');"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="btn btn-info"
                        >
                            Refer to Mediation
                        </button>

                    </form>

                @endif


                {{-- ================================================= --}}
                {{-- SCHEDULE HEARING --}}
                {{-- ================================================= --}}

                @if($caseStatus === 'For Mediation')

                    <div class="alert alert-primary mt-3">

                        <strong>
                            Case is {{ $caseStage }}
                        </strong>

                        <div class="small mt-1">
                            The case remains active until the current proceeding outcome is recorded.
                        </div>

                    </div>


                    @if($hasActiveScheduledHearing)

                        <div class="alert alert-warning">

                            This case already has an active scheduled hearing.

                            Complete or reschedule the current hearing before
                            creating another hearing.

                        </div>

                    @else

                        <h6 class="mb-3">
                            Schedule {{ $currentProceedingLabel }} Hearing
                        </h6>


                        @if($luponMembers->isEmpty())

                            <div class="alert alert-warning">
                                No active Lupon Member account is available.
                            </div>

                        @else

                            <form
                                method="POST"
                                action="{{ route(
                                    'blotter.mediation.schedule',
                                    $case
                                ) }}"
                            >

                                @csrf


                                <div class="row g-3">


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            Hearing Date *
                                        </label>

                                        <input
                                            type="date"
                                            name="scheduled_date"
                                            class="form-control"
                                            value="{{ old('scheduled_date') }}"
                                            min="{{ now()->toDateString() }}"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            Hearing Time *
                                        </label>

                                        <input
                                            type="time"
                                            name="scheduled_time"
                                            class="form-control"
                                            value="{{ old('scheduled_time') }}"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            Venue *
                                        </label>

                                        <input
                                            type="text"
                                            name="venue"
                                            class="form-control"
                                            value="{{
                                                old(
                                                    'venue',
                                                    'Barangay Hall'
                                                )
                                            }}"
                                            required
                                        >

                                    </div>


                                    <div class="col-md-6">

                                        <label class="form-label">
                                            Lupon Member *
                                        </label>

                                        <select
                                            name="lupon_member_id"
                                            class="form-select"
                                            required
                                        >

                                            <option value="">
                                                Select Lupon Member
                                            </option>


                                            @foreach(
                                                $luponMembers
                                                as $lupon
                                            )

                                                <option
                                                    value="{{ $lupon->id }}"
                                                    @selected(
                                                        old('lupon_member_id')
                                                        == $lupon->id
                                                    )
                                                >

                                                    {{ $lupon->name }}

                                                </option>

                                            @endforeach

                                        </select>

                                    </div>


                                    <div class="col-12">

                                        <label class="form-label">
                                            Initial {{ $currentProceedingLabel }} Notes
                                        </label>

                                        <textarea
                                            name="mediation_notes"
                                            class="form-control"
                                            rows="3"
                                        >{{ old('mediation_notes') }}</textarea>

                                    </div>


                                    <div class="col-12">

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                        >
                                            Schedule {{ $currentProceedingLabel }} Hearing
                                        </button>

                                    </div>

                                </div>

                            </form>

                        @endif

                    @endif

                @endif


                {{-- ================================================= --}}
                {{-- HEARING HISTORY --}}
                {{-- ================================================= --}}

                @if(
                    $case
                        ->mediationSessions
                        ->isNotEmpty()
                )

                    <hr class="my-4">


                    <h5 class="mb-3">
                        Proceeding History
                    </h5>


                    @foreach(
                        $case
                            ->mediationSessions
                            ->sortByDesc('hearing_number')
                        as $session
                    )

                        @php
                            $hearingStatusClass = match(
                                $session->status
                            ) {
                                'Scheduled'
                                    => 'text-bg-primary',

                                'Completed'
                                    => 'text-bg-success',

                                'Rescheduled'
                                    => 'text-bg-warning',

                                'Cancelled'
                                    => 'text-bg-danger',

                                'No Show'
                                    => 'text-bg-secondary',

                                default
                                    => 'text-bg-secondary',
                            };
                        @endphp


                        <div class="border rounded p-3 mb-4">


                            {{-- ===================================== --}}
                            {{-- HEARING HEADER --}}
                            {{-- ===================================== --}}

                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">

                                <div>

                                    <h5 class="mb-1">

                                        {{ $session->proceeding_type ?: 'Mediation' }} Hearing
                                        #{{ $session->hearing_number }}

                                    </h5>

                                    <span class="badge {{ $hearingStatusClass }}">
                                        {{ $session->status }}
                                    </span>

                                </div>


                                <div class="text-end">

                                    <strong>

                                        {{
                                            $session
                                                ->scheduled_date
                                                ?->format('F d, Y')
                                        }}

                                    </strong>


                                    @if($session->scheduled_time)

                                        <div class="small text-muted">

                                            {{
                                                date(
                                                    'h:i A',
                                                    strtotime(
                                                        $session->scheduled_time
                                                    )
                                                )
                                            }}

                                        </div>

                                    @endif

                                </div>

                            </div>


                            <div class="row g-3 mb-3">


                                <div class="col-md-6">

                                    <div class="text-muted small">
                                        Venue
                                    </div>

                                    <strong>
                                        {{ $session->venue }}
                                    </strong>

                                </div>


                                <div class="col-md-6">

                                    <div class="text-muted small">
                                        Assigned Lupon Member
                                    </div>

                                    <strong>

                                        {{
                                            $session
                                                ->luponMember
                                                ?->name
                                            ?? 'Not Assigned'
                                        }}

                                    </strong>

                                </div>


                                <div class="col-md-6">

                                    <div class="text-muted small">
                                        Created By
                                    </div>

                                    <div>

                                        {{
                                            $session
                                                ->creator
                                                ?->name
                                            ?? '—'
                                        }}

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="text-muted small">
                                        Hearing Status
                                    </div>

                                    <span class="badge {{ $hearingStatusClass }}">
                                        {{ $session->status }}
                                    </span>

                                </div>

                            </div>


                            @if($session->mediation_notes)

                                <div class="mb-3">

                                    <div class="text-muted small">
                                        Proceeding Notes
                                    </div>

                                    <div style="white-space: pre-line;">
                                        {{ $session->mediation_notes }}
                                    </div>

                                </div>

                            @endif


                            {{-- ===================================== --}}
                            {{-- SUMMONS --}}
                            {{-- ===================================== --}}

                            <hr>


                            <h6 class="mb-3">
                                Summons Tracking
                            </h6>


                            @forelse(
                                $session->summons
                                as $summons
                            )

                                @php
                                    $summonsClass = match(
                                        $summons->status
                                    ) {
                                        'Prepared'
                                            => 'text-bg-info',

                                        'Served'
                                            => 'text-bg-primary',

                                        'Received'
                                            => 'text-bg-success',

                                        'Failed Delivery'
                                            => 'text-bg-danger',

                                        default
                                            => 'text-bg-secondary',
                                    };
                                @endphp


                                <div class="border rounded p-3 mb-3">


                                    <div class="d-flex justify-content-between mb-3">

                                        <div>

                                            <strong>
                                                {{ $summons->recipient_name }}
                                            </strong>

                                            <div class="small text-muted">
                                                {{ $summons->recipient_type }}
                                            </div>

                                        </div>


                                        <span class="badge {{ $summonsClass }} align-self-start">
                                            {{ $summons->status }}
                                        </span>

                                    </div>


                                    @if(!$session->outcome)

                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'mediation.summons.update',
                                                $summons
                                            ) }}"
                                        >

                                            @csrf
                                            @method('PATCH')


                                            <div class="row g-2 align-items-end">


                                                <div class="col-md-4">

                                                    <label class="form-label">
                                                        Status
                                                    </label>

                                                    <select
                                                        name="status"
                                                        class="form-select"
                                                        required
                                                    >

                                                        @foreach([
                                                            'Not Prepared',
                                                            'Prepared',
                                                            'Served',
                                                            'Received',
                                                            'Failed Delivery'
                                                        ] as $summonsStatus)

                                                            <option
                                                                value="{{ $summonsStatus }}"
                                                                @selected(
                                                                    $summons->status
                                                                    ===
                                                                    $summonsStatus
                                                                )
                                                            >

                                                                {{ $summonsStatus }}

                                                            </option>

                                                        @endforeach

                                                    </select>

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label">
                                                        Delivery Notes
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="delivery_notes"
                                                        class="form-control"
                                                        value="{{
                                                            $summons
                                                                ->delivery_notes
                                                        }}"
                                                    >

                                                </div>


                                                <div class="col-md-2">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-outline-primary w-100"
                                                    >
                                                        Save
                                                    </button>

                                                </div>

                                            </div>

                                        </form>

                                    @else

                                        @if($summons->delivery_notes)

                                            <div class="small">

                                                <strong>
                                                    Delivery Notes:
                                                </strong>

                                                {{ $summons->delivery_notes }}

                                            </div>

                                        @endif

                                    @endif


                                    @if(
                                        $summons->prepared_at
                                        ||
                                        $summons->served_at
                                        ||
                                        $summons->received_at
                                    )

                                        <div class="small text-muted mt-3">


                                            @if($summons->prepared_at)

                                                <div>

                                                    Prepared:

                                                    {{
                                                        $summons
                                                            ->prepared_at
                                                            ->format(
                                                                'M d, Y h:i A'
                                                            )
                                                    }}

                                                </div>

                                            @endif


                                            @if($summons->served_at)

                                                <div>

                                                    Served:

                                                    {{
                                                        $summons
                                                            ->served_at
                                                            ->format(
                                                                'M d, Y h:i A'
                                                            )
                                                    }}

                                                </div>

                                            @endif


                                            @if($summons->received_at)

                                                <div>

                                                    Received:

                                                    {{
                                                        $summons
                                                            ->received_at
                                                            ->format(
                                                                'M d, Y h:i A'
                                                            )
                                                    }}

                                                </div>

                                            @endif

                                        </div>

                                    @endif

                                </div>

                            @empty

                                <div class="text-muted">
                                    No summons generated.
                                </div>

                            @endforelse


                            {{-- ===================================== --}}
                            {{-- ATTENDANCE --}}
                            {{-- ===================================== --}}

                            <hr class="my-4">


                            <h6 class="mb-3">
                                Hearing Attendance
                            </h6>


                            @forelse(
                                $session->attendees
                                as $attendee
                            )

                                <div class="border rounded p-3 mb-3">

                                    <div class="row g-3 align-items-end">


                                        <div class="col-md-4">

                                            <div class="text-muted small">
                                                Participant
                                            </div>

                                            <strong>
                                                {{ $attendee->name }}
                                            </strong>

                                            <div class="small text-muted">
                                                {{ $attendee->participant_type }}
                                            </div>

                                        </div>


                                        @if(!$session->outcome)

                                            <div class="col-md-8">

                                                <form
                                                    method="POST"
                                                    action="{{ route(
                                                        'mediation.attendance.update',
                                                        $attendee
                                                    ) }}"
                                                >

                                                    @csrf
                                                    @method('PATCH')


                                                    <div class="row g-2 align-items-end">


                                                        <div class="col-md-4">

                                                            <label class="form-label">
                                                                Attendance
                                                            </label>

                                                            <select
                                                                name="is_present"
                                                                class="form-select"
                                                                required
                                                            >

                                                                <option
                                                                    value="1"
                                                                    @selected(
                                                                        $attendee
                                                                            ->is_present
                                                                    )
                                                                >
                                                                    Present
                                                                </option>

                                                                <option
                                                                    value="0"
                                                                    @selected(
                                                                        !$attendee
                                                                            ->is_present
                                                                    )
                                                                >
                                                                    Absent
                                                                </option>

                                                            </select>

                                                        </div>


                                                        <div class="col-md-5">

                                                            <label class="form-label">
                                                                Remarks
                                                            </label>

                                                            <input
                                                                type="text"
                                                                name="remarks"
                                                                class="form-control"
                                                                value="{{
                                                                    $attendee
                                                                        ->remarks
                                                                }}"
                                                                placeholder="Optional remarks"
                                                            >

                                                        </div>


                                                        <div class="col-md-3">

                                                            <button
                                                                type="submit"
                                                                class="btn btn-outline-primary w-100"
                                                            >
                                                                Save
                                                            </button>

                                                        </div>

                                                    </div>

                                                </form>

                                            </div>

                                        @else

                                            <div class="col-md-8">

                                                @if($attendee->is_present)

                                                    <span class="badge text-bg-success">
                                                        Present
                                                    </span>

                                                @else

                                                    <span class="badge text-bg-danger">
                                                        Absent
                                                    </span>

                                                @endif


                                                @if($attendee->remarks)

                                                    <div class="small text-muted mt-2">
                                                        {{ $attendee->remarks }}
                                                    </div>

                                                @endif

                                            </div>

                                        @endif

                                    </div>

                                </div>

                            @empty

                                <div class="text-muted">
                                    No attendees recorded.
                                </div>

                            @endforelse


                            {{-- ===================================== --}}
                            {{-- CONDUCT HEARING / OUTCOME --}}
                            {{-- ===================================== --}}

                            <hr class="my-4">


                            @if($session->outcome)

                                {{-- ================================= --}}
                                {{-- COMPLETED OUTCOME --}}
                                {{-- ================================= --}}

                                <h6 class="mb-3">
                                    Mediation Outcome
                                </h6>


                                @php
                                    $outcomeClass = match(
                                        $session->outcome->outcome
                                    ) {
                                        'Settled'
                                            => 'alert-success',

                                        'Referred'
                                            => 'alert-dark',

                                        'No Agreement'
                                            => 'alert-warning',

                                        'Dismissed'
                                            => 'alert-secondary',

                                        'Rescheduled'
                                            => 'alert-info',

                                        default
                                            => 'alert-light',
                                    };
                                @endphp


                                <div class="alert {{ $outcomeClass }} mb-0">

                                    <h5 class="alert-heading">

                                        {{
                                            $session
                                                ->outcome
                                                ->outcome
                                        }}

                                    </h5>


                                    @if(
                                        $session
                                            ->outcome
                                            ->agreement_details
                                    )

                                        <div class="mb-3">

                                            <strong>
                                                Agreement Details
                                            </strong>

                                            <div style="white-space: pre-line;">

                                                {{
                                                    $session
                                                        ->outcome
                                                        ->agreement_details
                                                }}

                                            </div>

                                        </div>

                                    @endif


                                    @if(
                                        $session
                                            ->outcome
                                            ->referral_agency
                                    )

                                        <div class="mb-3">

                                            <strong>
                                                Referral Agency:
                                            </strong>

                                            {{
                                                $session
                                                    ->outcome
                                                    ->referral_agency
                                            }}

                                        </div>

                                    @endif


                                    @if(
                                        $session
                                            ->outcome
                                            ->remarks
                                    )

                                        <div class="mb-3">

                                            <strong>
                                                Remarks
                                            </strong>

                                            <div style="white-space: pre-line;">

                                                {{
                                                    $session
                                                        ->outcome
                                                        ->remarks
                                                }}

                                            </div>

                                        </div>

                                    @endif


                                    <hr>


                                    <div class="small">

                                        Recorded by:

                                        <strong>

                                            {{
                                                $session
                                                    ->outcome
                                                    ->recordedBy
                                                    ?->name
                                                ?? 'Unknown'
                                            }}

                                        </strong>

                                    </div>


                                    <div class="small">

                                        Recorded at:

                                        {{
                                            $session
                                                ->outcome
                                                ->recorded_at
                                                ?->format(
                                                    'F d, Y h:i A'
                                                )
                                        }}

                                    </div>

                                </div>


                            @elseif($session->status === 'Scheduled')


                                {{-- ================================= --}}
                                {{-- RECORD OUTCOME --}}
                                {{-- ================================= --}}

                                <h6 class="mb-3">
                                    Conduct Hearing / Record Outcome
                                </h6>


                                <div class="alert alert-light border">

                                    Update attendance first, then record the
                                    official result of this proceeding.

                                </div>


                                <form
                                    method="POST"
                                    action="{{ route(
                                        'mediation.outcome.store',
                                        $session
                                    ) }}"
                                    onsubmit="return confirm(
                                        'Record this proceeding outcome? This hearing will be closed.'
                                    );"
                                >

                                    @csrf


                                    <div class="row g-3">


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                Hearing Outcome *
                                            </label>

                                            <select
                                                name="outcome"
                                                class="form-select mediation-outcome-select"
                                                required
                                            >

                                                <option value="">
                                                    Select Outcome
                                                </option>

                                                <option
                                                    value="Settled"
                                                    @selected(
                                                        old('outcome')
                                                        === 'Settled'
                                                    )
                                                >
                                                    Settled
                                                </option>

                                                <option
                                                    value="Referred"
                                                    @selected(
                                                        old('outcome')
                                                        === 'Referred'
                                                    )
                                                >
                                                    Referred
                                                </option>

                                                <option
                                                    value="Rescheduled"
                                                    @selected(
                                                        old('outcome')
                                                        === 'Rescheduled'
                                                    )
                                                >
                                                    Rescheduled
                                                </option>

                                                <option
                                                    value="No Agreement"
                                                    @selected(
                                                        old('outcome')
                                                        === 'No Agreement'
                                                    )
                                                >
                                                    No Agreement
                                                </option>

                                                <option
                                                    value="Dismissed"
                                                    @selected(
                                                        old('outcome')
                                                        === 'Dismissed'
                                                    )
                                                >
                                                    Dismissed
                                                </option>

                                            </select>

                                            <div class="form-text">
                                                @if(($session->proceeding_type ?: 'Mediation') === 'Mediation')
                                                    No Agreement automatically advances the case to For Pangkat/Conciliation.
                                                @else
                                                    No Agreement advances the case to For Further Action/CFA.
                                                @endif
                                            </div>

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                Referral Agency
                                            </label>

                                            <input
                                                type="text"
                                                name="referral_agency"
                                                class="form-control"
                                                value="{{
                                                    old(
                                                        'referral_agency'
                                                    )
                                                }}"
                                                placeholder="Example: Police, Court, other agency"
                                            >

                                            <div class="form-text">

                                                Required only when the outcome is Referred.

                                            </div>

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                Settlement / Agreement Details
                                            </label>

                                            <textarea
                                                name="agreement_details"
                                                class="form-control"
                                                rows="4"
                                                placeholder="Enter the terms agreed upon by the parties."
                                            >{{ old('agreement_details') }}</textarea>

                                            <div class="form-text">
                                                Required when the outcome is Settled.
                                            </div>

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                Outcome Remarks
                                            </label>

                                            <textarea
                                                name="remarks"
                                                class="form-control"
                                                rows="3"
                                                placeholder="Additional hearing remarks"
                                            >{{ old('remarks') }}</textarea>

                                        </div>


                                        <div class="col-12">

                                            <button
                                                type="submit"
                                                class="btn btn-success"
                                            >
                                                Record Proceeding Outcome
                                            </button>

                                        </div>

                                    </div>

                                </form>

                            @endif

                        </div>

                    @endforeach

                @endif

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- REMARKS --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Case Remarks
                </strong>
            </div>

            <div class="card-body">

                @if($case->remarks)

                    <div style="white-space: pre-line;">
                        {{ $case->remarks }}
                    </div>

                @else

                    <span class="text-muted">
                        No remarks recorded.
                    </span>

                @endif

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- ASSIGNMENT HISTORY --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm">

            <div class="card-header bg-white d-flex justify-content-between">

                <strong>
                    Assignment History
                </strong>

                <span class="badge text-bg-secondary">

                    {{
                        $case
                            ->assignments
                            ->count()
                    }}

                </span>

            </div>


            <div class="card-body">

                @forelse(
                    $case
                        ->assignments
                        ->sortByDesc('assigned_at')
                    as $assignment
                )

                    <div class="border rounded p-3 mb-3">

                        <div class="row g-3">


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    Assigned Officer
                                </div>

                                <strong>

                                    {{
                                        $assignment
                                            ->assignedOfficer
                                            ?->name
                                        ?? 'Unknown'
                                    }}

                                </strong>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    Assigned By
                                </div>

                                <strong>

                                    {{
                                        $assignment
                                            ->assignedBy
                                            ?->name
                                        ?? 'Unknown'
                                    }}

                                </strong>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    Assigned
                                </div>

                                <div>

                                    {{
                                        $assignment
                                            ->assigned_at
                                            ?->format(
                                                'M d, Y h:i A'
                                            )
                                    }}

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    Assignment Status
                                </div>


                                @if($assignment->completed_at)

                                    <span class="badge text-bg-secondary">
                                        Completed
                                    </span>

                                @else

                                    <span class="badge text-bg-success">
                                        Active
                                    </span>

                                @endif

                            </div>


                            @if($assignment->assignment_notes)

                                <div class="col-12">

                                    <div class="text-muted small">
                                        Notes
                                    </div>

                                    {{ $assignment->assignment_notes }}

                                </div>

                            @endif

                        </div>

                    </div>

                @empty

                    <span class="text-muted">
                        No assignment history.
                    </span>

                @endforelse

            </div>

        </div>

    </div>



    {{-- ========================================================= --}}
    {{-- RIGHT COLUMN --}}
    {{-- ========================================================= --}}

    <div class="col-lg-4">


        {{-- ========================================================= --}}
        {{-- CASE INFORMATION --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Case Information
                </strong>
            </div>

            <div class="card-body">


                <div class="mb-3">

                    <div class="text-muted small">
                        Reference Number
                    </div>

                    <strong>
                        {{ $case->reference_number }}
                    </strong>

                </div>


                <div class="mb-3">

                    <div class="text-muted small">
                        Current Status
                    </div>

                    <span class="badge {{ $statusClass }}">
                        {{ $caseStatus }}
                    </span>

                </div>


                <div class="mb-3">

                    <div class="text-muted small">
                        Reported At
                    </div>

                    <div>

                        {{
                            $case
                                ->reported_at
                                ?->format(
                                    'F d, Y h:i A'
                                )
                            ?? '—'
                        }}

                    </div>

                </div>


                <div class="mb-3">

                    <div class="text-muted small">
                        Encoded By
                    </div>

                    <strong>
                        {{ $case->creator?->name ?? '—' }}
                    </strong>


                    @if($case->creator?->role)

                        <div class="small text-muted">

                            {{
                                $case
                                    ->creator
                                    ->role
                                    ->name
                            }}

                        </div>

                    @endif

                </div>


                @if($case->closed_at)

                    <div>

                        <div class="text-muted small">
                            Closed At
                        </div>

                        <div>

                            {{
                                $case
                                    ->closed_at
                                    ->format(
                                        'F d, Y h:i A'
                                    )
                            }}

                        </div>

                    </div>

                @endif

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- CASE ASSIGNMENT --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <strong>
                    Case Assignment
                </strong>
            </div>

            <div class="card-body">


                @if($case->currentAssignment)

                    <div class="alert alert-light border">

                        <div class="small text-muted">
                            Currently Assigned To
                        </div>

                        <strong>

                            {{
                                $case
                                    ->currentAssignment
                                    ->assignedOfficer
                                    ?->name
                                ?? 'Unknown'
                            }}

                        </strong>


                        @if(
                            $case
                                ->currentAssignment
                                ->assignedOfficer
                                ?->role
                        )

                            <div class="small text-muted">

                                {{
                                    $case
                                        ->currentAssignment
                                        ->assignedOfficer
                                        ->role
                                        ->name
                                }}

                            </div>

                        @endif


                        <hr>


                        <div class="small">

                            Assigned:

                            {{
                                $case
                                    ->currentAssignment
                                    ->assigned_at
                                    ?->format(
                                        'M d, Y h:i A'
                                    )
                            }}

                        </div>

                    </div>

                @else

                    <div class="alert alert-warning">
                        No active officer assignment.
                    </div>

                @endif


                @if(
                    !in_array(
                        $caseStatus,
                        $closedStatuses,
                        true
                    )
                    &&
                    $caseStatus !== 'For Mediation'
                )

                    <form
                        method="POST"
                        action="{{ route(
                            'blotter.assign',
                            $case
                        ) }}"
                    >

                        @csrf


                        <div class="mb-3">

                            <label class="form-label">
                                Councilor *
                            </label>

                            <select
                                name="assigned_to"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Councilor
                                </option>


                                @foreach(
                                    $councilors
                                    as $councilor
                                )

                                    <option
                                        value="{{ $councilor->id }}"
                                    >

                                        {{ $councilor->name }}

                                    </option>

                                @endforeach

                            </select>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Assignment Notes
                            </label>

                            <textarea
                                name="assignment_notes"
                                class="form-control"
                                rows="3"
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                            @disabled(
                                $councilors
                                    ->isEmpty()
                            )
                        >

                            @if($case->currentAssignment)

                                Reassign Case

                            @else

                                Assign Case

                            @endif

                        </button>

                    </form>

                @elseif($caseStatus === 'For Mediation')

                    <div class="small text-muted">

                        Investigation assignment is closed while
                        the case is under mediation.

                    </div>

                @endif

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- COMPLAINANTS --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white d-flex justify-content-between">

                <strong>
                    Complainant
                </strong>

                <span class="badge text-bg-primary">

                    {{
                        $case
                            ->complainants
                            ->count()
                    }}

                </span>

            </div>


            <div class="card-body">

                @forelse($case->complainants as $person)

                    <div
                        class="{{
                            !$loop->last
                                ? 'border-bottom pb-3 mb-3'
                                : ''
                        }}"
                    >

                        <strong>

                            {{
                                trim(
                                    $person->first_name
                                    . ' '
                                    . ($person->middle_name ?? '')
                                    . ' '
                                    . $person->last_name
                                    . ' '
                                    . ($person->suffix ?? '')
                                )
                            }}

                        </strong>


                        @if($person->resident)

                            <div class="small text-success">
                                Registered Resident
                            </div>

                            <div class="small text-muted">

                                {{
                                    $person
                                        ->resident
                                        ->resident_code
                                }}

                            </div>

                        @else

                            <div class="small text-muted">
                                Non-registered resident
                            </div>

                        @endif


                        @if($person->contact_number)

                            <div class="small mt-2">

                                <strong>
                                    Contact:
                                </strong>

                                {{ $person->contact_number }}

                            </div>

                        @endif


                        @if($person->address)

                            <div class="small mt-2">

                                <strong>
                                    Address:
                                </strong>

                                {{ $person->address }}

                            </div>

                        @endif


                        <div class="small mt-1">

                            <strong>
                                Residency:
                            </strong>

                            @if($person->is_san_jose_resident)

                                <span class="text-success">
                                    Barangay San Jose
                                </span>

                            @else

                                <span class="text-muted">
                                    Outside Barangay San Jose
                                </span>

                            @endif

                        </div>


                        @if(
                            $person->is_san_jose_resident
                            &&
                            $person->sitio
                        )

                            <div class="small mt-1">

                                <strong>
                                    Sitio:
                                </strong>

                                {{ $person->sitio }}

                            </div>

                        @endif


                        @if(
                            $person->is_san_jose_resident
                            &&
                            $person->house_number
                        )

                            <div class="small mt-1">

                                <strong>
                                    House Number:
                                </strong>

                                {{ $person->house_number }}

                            </div>

                        @endif

                    </div>

                @empty

                    <span class="text-muted">
                        No complainant recorded.
                    </span>

                @endforelse

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- RESPONDENTS --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white d-flex justify-content-between">

                <strong>
                    Respondent
                </strong>

                <span class="badge text-bg-danger">

                    {{
                        $case
                            ->respondents
                            ->count()
                    }}

                </span>

            </div>


            <div class="card-body">

                @forelse($case->respondents as $person)

                    <div
                        class="{{
                            !$loop->last
                                ? 'border-bottom pb-3 mb-3'
                                : ''
                        }}"
                    >

                        <strong>

                            {{
                                trim(
                                    $person->first_name
                                    . ' '
                                    . ($person->middle_name ?? '')
                                    . ' '
                                    . $person->last_name
                                    . ' '
                                    . ($person->suffix ?? '')
                                )
                            }}

                        </strong>


                        @if($person->resident)

                            <div class="small text-success">
                                Registered Resident
                            </div>

                            <div class="small text-muted">

                                {{
                                    $person
                                        ->resident
                                        ->resident_code
                                }}

                            </div>

                        @else

                            <div class="small text-muted">
                                Non-registered resident
                            </div>

                        @endif


                        @if($person->contact_number)

                            <div class="small mt-2">

                                <strong>
                                    Contact:
                                </strong>

                                {{ $person->contact_number }}

                            </div>

                        @endif


                        @if($person->address)

                            <div class="small mt-2">

                                <strong>
                                    Address:
                                </strong>

                                {{ $person->address }}

                            </div>

                        @endif


                        <div class="small mt-1">

                            <strong>
                                Residency:
                            </strong>

                            @if($person->is_san_jose_resident)

                                <span class="text-success">
                                    Barangay San Jose
                                </span>

                            @else

                                <span class="text-muted">
                                    Outside Barangay San Jose
                                </span>

                            @endif

                        </div>


                        @if(
                            $person->is_san_jose_resident
                            &&
                            $person->sitio
                        )

                            <div class="small mt-1">

                                <strong>
                                    Sitio:
                                </strong>

                                {{ $person->sitio }}

                            </div>

                        @endif


                        @if(
                            $person->is_san_jose_resident
                            &&
                            $person->house_number
                        )

                            <div class="small mt-1">

                                <strong>
                                    House Number:
                                </strong>

                                {{ $person->house_number }}

                            </div>

                        @endif

                    </div>

                @empty

                    <span class="text-muted">
                        No respondent recorded.
                    </span>

                @endforelse

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- WITNESS MANAGEMENT --}}
        {{-- ========================================================= --}}

        @php
            $witnessManagementAllowed =
                auth()->user()->can('manageWitnesses', $case)
                && !in_array(
                    $caseStatus,
                    $closedStatuses,
                    true
                );
        @endphp

        <div class="card shadow-sm">

            <div class="card-header bg-white d-flex justify-content-between align-items-center">

                <strong>
                    Witness Management
                </strong>

                <span class="badge text-bg-secondary">
                    {{ $case->witnesses->count() }}
                </span>

            </div>


            <div class="card-body">

                {{-- ================================================= --}}
                {{-- ADD WITNESS --}}
                {{-- ================================================= --}}

                @if($witnessManagementAllowed)

                    <h6 class="mb-3">
                        Add Witness
                    </h6>


                    <form
                        method="POST"
                        action="{{ route('blotter.witnesses.store', $case) }}"
                    >

                        @csrf


                        <div class="mb-3">

                            @include('blotter._person-picker', [
                                'prefix' => 'witness',
                                'label' => 'Witness from People Directory',
                                'fieldName' => 'resident_id',
                                'required' => false,
                                'helpText' => 'Optional: search the People Directory. Leave this blank to enter witness details manually below.',
                            ])

                        </div>


                        <div class="row g-3">

                            <div class="col-md-6">

                                <label class="form-label">
                                    First Name
                                </label>

                                <input
                                    type="text"
                                    name="first_name"
                                    class="form-control"
                                    value="{{ old('first_name') }}"
                                >

                            </div>


                            <div class="col-md-6">

                                <label class="form-label">
                                    Middle Name
                                </label>

                                <input
                                    type="text"
                                    name="middle_name"
                                    class="form-control"
                                    value="{{ old('middle_name') }}"
                                >

                            </div>


                            <div class="col-md-6">

                                <label class="form-label">
                                    Last Name
                                </label>

                                <input
                                    type="text"
                                    name="last_name"
                                    class="form-control"
                                    value="{{ old('last_name') }}"
                                >

                            </div>


                            <div class="col-md-6">

                                <label class="form-label">
                                    Suffix
                                </label>

                                <input
                                    type="text"
                                    name="suffix"
                                    class="form-control"
                                    value="{{ old('suffix') }}"
                                    placeholder="Jr., Sr., III"
                                >

                            </div>


                            <div class="col-md-6">

                                <label class="form-label">
                                    Contact Number
                                </label>

                                <input
                                    type="text"
                                    name="contact_number"
                                    class="form-control"
                                    value="{{ old('contact_number') }}"
                                >

                            </div>


                            <div class="col-md-6">

                                <label class="form-label">
                                    Address
                                </label>

                                <input
                                    type="text"
                                    name="address"
                                    class="form-control"
                                    value="{{ old('address') }}"
                                >

                            </div>


                            <div class="col-12">

                                <label class="form-label">
                                    Witness Statement
                                </label>

                                <textarea
                                    name="statement"
                                    class="form-control"
                                    rows="4"
                                >{{ old('statement') }}</textarea>

                            </div>


                            <div class="col-12">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Add Witness
                                </button>

                            </div>

                        </div>

                    </form>


                    <hr class="my-4">

                @endif


                {{-- ================================================= --}}
                {{-- EXISTING WITNESSES --}}
                {{-- ================================================= --}}

                <h6 class="mb-3">
                    Existing Witnesses
                </h6>


                @forelse($case->witnesses as $witness)

                    <div class="border rounded p-3 mb-3">


                        <div class="mb-3">

                            <strong>
                                {{ $witness->full_name }}
                            </strong>


                            @if($witness->resident)

                                <div class="small text-success">
                                    Registered Resident
                                </div>

                                @if($witness->resident->resident_code)

                                    <div class="small text-muted">
                                        {{ $witness->resident->resident_code }}
                                    </div>

                                @endif

                            @else

                                <div class="small text-muted">
                                    Non-registered Witness
                                </div>

                            @endif

                        </div>


                        @if($witness->contact_number)

                            <div class="small mb-2">

                                <strong>
                                    Contact:
                                </strong>

                                {{ $witness->contact_number }}

                            </div>

                        @endif


                        @if($witness->address)

                            <div class="small mb-2">

                                <strong>
                                    Address:
                                </strong>

                                {{ $witness->address }}

                            </div>

                        @endif


                        <div class="mt-3">

                            <div class="text-muted small mb-1">
                                Witness Statement
                            </div>


                            @if($witness->statement)

                                <div style="white-space: pre-line;">
                                    {{ $witness->statement }}
                                </div>

                            @else

                                <span class="text-muted">
                                    No statement recorded.
                                </span>

                            @endif

                        </div>


                        {{-- ========================================= --}}
                        {{-- EDIT / REMOVE WITNESS --}}
                        {{-- ========================================= --}}

                        @if($witnessManagementAllowed)

                            <hr>


                            <details class="mb-3">

                                <summary class="btn btn-sm btn-outline-primary">
                                    Edit Witness
                                </summary>


                                <form
                                    method="POST"
                                    action="{{ route('witnesses.update', $witness) }}"
                                    class="mt-3"
                                >

                                    @csrf
                                    @method('PUT')


                                    <div class="row g-3">

                                        <div class="col-md-6">

                                            <label class="form-label">
                                                First Name *
                                            </label>

                                            <input
                                                type="text"
                                                name="first_name"
                                                class="form-control"
                                                value="{{ $witness->first_name }}"
                                                required
                                            >

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                Middle Name
                                            </label>

                                            <input
                                                type="text"
                                                name="middle_name"
                                                class="form-control"
                                                value="{{ $witness->middle_name }}"
                                            >

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                Last Name *
                                            </label>

                                            <input
                                                type="text"
                                                name="last_name"
                                                class="form-control"
                                                value="{{ $witness->last_name }}"
                                                required
                                            >

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                Suffix
                                            </label>

                                            <input
                                                type="text"
                                                name="suffix"
                                                class="form-control"
                                                value="{{ $witness->suffix }}"
                                            >

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                Contact Number
                                            </label>

                                            <input
                                                type="text"
                                                name="contact_number"
                                                class="form-control"
                                                value="{{ $witness->contact_number }}"
                                            >

                                        </div>


                                        <div class="col-md-6">

                                            <label class="form-label">
                                                Address
                                            </label>

                                            <input
                                                type="text"
                                                name="address"
                                                class="form-control"
                                                value="{{ $witness->address }}"
                                            >

                                        </div>


                                        <div class="col-12">

                                            <label class="form-label">
                                                Witness Statement
                                            </label>

                                            <textarea
                                                name="statement"
                                                class="form-control"
                                                rows="4"
                                            >{{ $witness->statement }}</textarea>

                                        </div>


                                        <div class="col-12">

                                            <button
                                                type="submit"
                                                class="btn btn-success"
                                            >
                                                Save Changes
                                            </button>

                                        </div>

                                    </div>

                                </form>

                            </details>


                            <form
                                method="POST"
                                action="{{ route('witnesses.destroy', $witness) }}"
                                onsubmit="return confirm('Remove this witness from the case?');"
                            >

                                @csrf
                                @method('DELETE')


                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                >
                                    Remove Witness
                                </button>

                            </form>

                        @endif

                    </div>

                @empty

                    <span class="text-muted">
                        No witnesses recorded.
                    </span>

                @endforelse

            </div>

        </div>

    </div>

</div>

@endsection