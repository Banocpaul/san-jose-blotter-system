@extends('layouts.app')

@section('title', 'Hearing Schedules')
@section('page-title', 'Hearing Schedules')

@section('content')
@php
    $cards = [
        [
            'label' => 'Upcoming',
            'value' => $kpis['upcoming'],
            'view' => 'upcoming',
            'icon' => 'bi-calendar2-week',
            'class' => 'border-primary',
        ],
        [
            'label' => "Today's Schedule",
            'value' => $kpis['today'],
            'view' => 'today',
            'icon' => 'bi-calendar-check',
            'class' => 'border-warning',
        ],
        [
            'label' => 'Completed',
            'value' => $kpis['completed'],
            'view' => 'completed',
            'icon' => 'bi-check2-circle',
            'class' => 'border-success',
        ],
    ];

    $personName = function ($person) {
        return trim(collect([
            $person->first_name,
            $person->middle_name,
            $person->last_name,
            $person->suffix,
        ])->filter()->implode(' '));
    };

    $isScheduleManager =
        in_array(
            $roleSlug,
            [
                'barangay_captain',
                'secretary',
            ],
            true
        );
@endphp


<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <h3 class="mb-1">
            Hearing Schedules
        </h3>

        <div class="text-muted">
            Central schedule for mediation and Pangkat conciliation hearings.
        </div>

    </div>


    @if($isScheduleManager)

        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#createHearingModal"
            @disabled($eligibleCases->isEmpty() || $luponMembers->isEmpty())
        >
            <i class="bi bi-calendar-plus me-1"></i>
            Create Schedule
        </button>

    @endif

</div>


@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


@if($errors->any())

    <div class="alert alert-danger">
        <strong>Unable to save the hearing schedule.</strong>

        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>

@endif


@if($isScheduleManager && $eligibleCases->isEmpty())

    <div class="alert alert-info">

        <i class="bi bi-info-circle me-1"></i>

        There are currently no open cases ready for a new mediation or
        Pangkat conciliation hearing. A case must already be in the appropriate
        workflow stage and must not have another active scheduled hearing.

    </div>

@endif


@if($isScheduleManager && $luponMembers->isEmpty())

    <div class="alert alert-warning">

        <i class="bi bi-exclamation-triangle me-1"></i>

        No active Lupon Member account is available. Create or activate a Lupon
        account before scheduling a hearing.

    </div>

@endif


<div class="row g-3 mb-4">

    @foreach($cards as $card)

        <div class="col-12 col-md-4">

            <a
                href="{{ route('hearings.index', ['view' => $card['view']]) }}"
                class="text-decoration-none"
            >

                <div class="card shadow-sm h-100 border-start border-4 {{ $card['class'] }}">

                    <div class="card-body d-flex justify-content-between align-items-center gap-3">

                        <div>

                            <div class="text-muted small mb-1">
                                {{ $card['label'] }}
                            </div>

                            <div class="fs-3 fw-bold text-dark">
                                {{ number_format($card['value']) }}
                            </div>

                        </div>

                        <i class="bi {{ $card['icon'] }} fs-2 text-muted"></i>

                    </div>

                </div>

            </a>

        </div>

    @endforeach

</div>


<div class="card shadow-sm mb-4">

    <div class="card-body">

        <form
            method="GET"
            action="{{ route('hearings.index') }}"
            class="row g-2 align-items-end"
        >

            <div class="col-12 col-lg-7">

                <label class="form-label">
                    Search
                </label>

                <input
                    type="search"
                    name="search"
                    class="form-control"
                    value="{{ request('search') }}"
                    placeholder="Case number, complainant, respondent, or assigned Lupon"
                >

            </div>


            <div class="col-12 col-md-6 col-lg-3">

                <label class="form-label">
                    Schedule View
                </label>

                <select
                    name="view"
                    class="form-select"
                >

                    <option value="">
                        All Schedules
                    </option>

                    <option
                        value="upcoming"
                        @selected(request('view') === 'upcoming')
                    >
                        Upcoming
                    </option>

                    <option
                        value="today"
                        @selected(request('view') === 'today')
                    >
                        Today's Schedule
                    </option>

                    <option
                        value="completed"
                        @selected(request('view') === 'completed')
                    >
                        Completed
                    </option>

                </select>

            </div>


            <div class="col-6 col-lg-1 d-grid">

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Filter
                </button>

            </div>


            <div class="col-6 col-lg-1 d-grid">

                <a
                    href="{{ route('hearings.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Clear
                </a>

            </div>

        </form>

    </div>

</div>


<div class="card shadow-sm">

    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">

        <strong>
            Hearing Schedule List
        </strong>

        <span class="text-muted small">
            {{ number_format($sessions->total()) }} schedule(s)
        </span>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead class="table-light">

                <tr>
                    <th>Case Reference</th>
                    <th>Proceeding & Schedule</th>
                    <th>Parties Involved</th>
                    <th>Assigned Lupon</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>

            </thead>


            <tbody>

                @forelse($sessions as $session)

                    @php
                        $case = $session->blotterCase;

                        $isToday =
                            $session
                                ->scheduled_date
                                ?->isToday();

                        $isUpcoming =
                            $session->status === 'Scheduled'
                            &&
                            $session
                                ->scheduled_date
                                ?->isFuture();
                    @endphp


                    <tr>

                        <td>

                            <div class="fw-semibold">
                                {{ $case?->reference_number ?? '—' }}
                            </div>

                            <div class="small text-muted">
                                {{ $case?->incidentType?->name ?? 'Unclassified' }}
                            </div>

                        </td>


                        <td style="min-width: 230px;">

                            <div class="mb-1">

                                <span class="badge text-bg-light border">
                                    {{ $session->proceeding_type ?: 'Mediation' }}
                                </span>

                                <span class="small text-muted">
                                    Hearing #{{ $session->hearing_number }}
                                </span>

                            </div>

                            <div class="fw-semibold">

                                {{ $session->scheduled_date?->format('M d, Y') ?? '—' }}

                                @if($session->scheduled_time)
                                    - {{ date('h:i A', strtotime($session->scheduled_time)) }}
                                @endif

                            </div>

                            <div class="small text-muted">
                                {{ $session->venue ?: 'Barangay Hall' }}
                            </div>

                        </td>


                        <td style="min-width: 240px;">

                            <div class="small">

                                <strong>Complainant:</strong>

                                {{ $case?->complainants?->map($personName)->filter()->join(', ') ?: '—' }}

                            </div>

                            <div class="small mt-1">

                                <strong>Respondent:</strong>

                                {{ $case?->respondents?->map($personName)->filter()->join(', ') ?: '—' }}

                            </div>

                        </td>


                        <td>

                            <div class="fw-semibold">
                                {{ $session->luponMember?->name ?? 'Not assigned' }}
                            </div>

                            <div class="small text-muted">

                                {{
                                    $session->proceeding_type === 'Pangkat Conciliation'
                                        ? 'Pangkat / Lupon'
                                        : 'Lupon / Mediator'
                                }}

                            </div>

                        </td>


                        <td>

                            @if($session->status === 'Completed')

                                <span class="badge text-bg-success">
                                    Completed
                                </span>

                            @elseif($isToday)

                                <span class="badge text-bg-warning">
                                    Today
                                </span>

                            @elseif($isUpcoming)

                                <span class="badge text-bg-primary">
                                    Upcoming
                                </span>

                            @else

                                <span class="badge text-bg-secondary">
                                    {{ $session->status }}
                                </span>

                            @endif


                            @if($session->outcome)

                                <div class="small text-muted mt-1">
                                    {{ $session->outcome->outcome }}
                                </div>

                            @endif

                        </td>


                        <td class="text-end">

                            @if($case)

                                <a
                                    href="{{ route('cases.show', $case) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    View Case
                                </a>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted py-5"
                        >
                            No hearing schedules found.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    @if($sessions->hasPages())

        <div class="card-footer bg-white">

            {{ $sessions->links() }}

        </div>

    @endif

</div>


@if($isScheduleManager)

    <div
        class="modal fade"
        id="createHearingModal"
        tabindex="-1"
        aria-labelledby="createHearingModalLabel"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-lg modal-dialog-scrollable">

            <div class="modal-content">

                <form
                    id="createHearingForm"
                    method="POST"
                    action=""
                >

                    @csrf


                    <div class="modal-header">

                        <div>

                            <h5
                                class="modal-title"
                                id="createHearingModalLabel"
                            >
                                Create Hearing Schedule
                            </h5>

                            <div class="small text-muted">
                                Schedule an eligible case for mediation or Pangkat conciliation.
                            </div>

                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"
                        ></button>

                    </div>


                    <div class="modal-body">

                        <div class="row g-3">


                            <div class="col-12">

                                <label
                                    for="hearing_case_id"
                                    class="form-label"
                                >
                                    Case
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    id="hearing_case_id"
                                    name="hearing_case_id"
                                    class="form-select"
                                    required
                                    @disabled($eligibleCases->isEmpty())
                                >

                                    <option value="">
                                        Select an eligible case
                                    </option>


                                    @foreach($eligibleCases as $case)

                                        @php
                                            $proceedingType =
                                                $case->case_stage === \App\Enums\CaseStage::ForPangkatConciliation
                                                    ? 'Pangkat Conciliation'
                                                    : 'Mediation';
                                        @endphp

                                        <option
                                            value="{{ $case->id }}"
                                            data-action="{{ route('blotter.mediation.schedule', $case) }}"
                                            data-proceeding="{{ $proceedingType }}"
                                            @selected((string) old('hearing_case_id') === (string) $case->id)
                                        >
                                            {{ $case->reference_number }}
                                            — {{ $proceedingType }}
                                            — {{ $case->incidentType?->name ?? 'Unclassified' }}
                                        </option>

                                    @endforeach

                                </select>

                                <div class="form-text">
                                    Only open cases already referred to mediation or Pangkat conciliation and without another active hearing are shown.
                                </div>

                            </div>


                            <div class="col-12 col-md-6">

                                <label
                                    for="hearing_proceeding_type"
                                    class="form-label"
                                >
                                    Proceeding Type
                                </label>

                                <input
                                    id="hearing_proceeding_type"
                                    type="text"
                                    class="form-control"
                                    value=""
                                    placeholder="Automatically based on case stage"
                                    readonly
                                >

                            </div>


                            <div class="col-12 col-md-6">

                                <label
                                    for="lupon_member_id"
                                    class="form-label"
                                >
                                    Assigned Lupon Member
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    id="lupon_member_id"
                                    name="lupon_member_id"
                                    class="form-select"
                                    required
                                    @disabled($luponMembers->isEmpty())
                                >

                                    <option value="">
                                        Select Lupon Member
                                    </option>

                                    @foreach($luponMembers as $member)

                                        <option
                                            value="{{ $member->id }}"
                                            @selected((string) old('lupon_member_id') === (string) $member->id)
                                        >
                                            {{ $member->name }}
                                        </option>

                                    @endforeach

                                </select>

                            </div>


                            <div class="col-12 col-md-6">

                                <label
                                    for="scheduled_date"
                                    class="form-label"
                                >
                                    Hearing Date
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="scheduled_date"
                                    type="date"
                                    name="scheduled_date"
                                    class="form-control"
                                    min="{{ now()->toDateString() }}"
                                    value="{{ old('scheduled_date') }}"
                                    required
                                >

                            </div>


                            <div class="col-12 col-md-6">

                                <label
                                    for="scheduled_time"
                                    class="form-label"
                                >
                                    Hearing Time
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="scheduled_time"
                                    type="time"
                                    name="scheduled_time"
                                    class="form-control"
                                    value="{{ old('scheduled_time') }}"
                                    required
                                >

                            </div>


                            <div class="col-12">

                                <label
                                    for="venue"
                                    class="form-label"
                                >
                                    Venue
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    id="venue"
                                    type="text"
                                    name="venue"
                                    class="form-control"
                                    value="{{ old('venue', 'Barangay Hall') }}"
                                    maxlength="255"
                                    required
                                >

                            </div>


                            <div class="col-12">

                                <label
                                    for="mediation_notes"
                                    class="form-label"
                                >
                                    Schedule Notes
                                </label>

                                <textarea
                                    id="mediation_notes"
                                    name="mediation_notes"
                                    class="form-control"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="Optional instructions or notes for the hearing."
                                >{{ old('mediation_notes') }}</textarea>

                            </div>


                        </div>

                    </div>


                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            @disabled($eligibleCases->isEmpty() || $luponMembers->isEmpty())
                        >
                            <i class="bi bi-calendar-check me-1"></i>
                            Save Schedule
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

@endif

@endsection


@if($isScheduleManager)

    @push('scripts')

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form =
                    document.getElementById('createHearingForm');

                const caseSelect =
                    document.getElementById('hearing_case_id');

                const proceedingInput =
                    document.getElementById('hearing_proceeding_type');

                const modalElement =
                    document.getElementById('createHearingModal');


                if (!form || !caseSelect || !proceedingInput) {
                    return;
                }


                const syncSelectedCase = function () {
                    const selectedOption =
                        caseSelect.options[
                            caseSelect.selectedIndex
                        ];

                    const action =
                        selectedOption?.dataset?.action || '';

                    const proceeding =
                        selectedOption?.dataset?.proceeding || '';

                    form.action = action;

                    proceedingInput.value = proceeding;
                };


                caseSelect.addEventListener(
                    'change',
                    syncSelectedCase
                );


                form.addEventListener(
                    'submit',
                    function (event) {
                        syncSelectedCase();

                        if (!form.action) {
                            event.preventDefault();

                            caseSelect.focus();

                            return;
                        }
                    }
                );


                syncSelectedCase();


                @if($errors->any() && old('hearing_case_id'))

                    if (modalElement) {
                        const modal =
                            bootstrap.Modal.getOrCreateInstance(
                                modalElement
                            );

                        modal.show();
                    }

                @endif
            });
        </script>

    @endpush

@endif
