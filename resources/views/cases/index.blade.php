@extends('layouts.app')

@section('title', 'Case Management')

@section('content')

<div
    class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"
>
    <div>
        <h3 class="mb-1">
            Case Management
        </h3>

        <div class="text-muted">
            Manage case workflow, current stage, record status,
            assignment, mediation, and case progress.
        </div>
    </div>

    @can('create', App\Models\BlotterCase::class)

        <a
            href="{{ route('blotter.create') }}"
            class="btn btn-primary"
        >
            <i class="bi bi-file-earmark-plus me-1"></i>

            New Blotter Record
        </a>

    @endcan
</div>


@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


{{-- ========================================================= --}}
{{-- CURRENT STAGE KPI CARDS --}}
{{-- ========================================================= --}}

<div class="row g-3 mb-4">

    @foreach($kpiStages as $stage)

        <div class="col-12 col-sm-6 col-xl">

            <a
                href="{{ route('cases.index', [
                    'stage' => $stage->value
                ]) }}"
                class="text-decoration-none"
            >

                <div
                    class="card shadow-sm h-100 border-0"
                >

                    <div class="card-body">

                        <div
                            class="text-muted small mb-2"
                        >
                            {{ $stage->value }}
                        </div>

                        <div
                            class="d-flex justify-content-between align-items-center"
                        >

                            <div
                                class="fs-3 fw-bold text-dark"
                            >
                                {{
                                    number_format(
                                        $stageCounts[
                                            $stage->value
                                        ] ?? 0
                                    )
                                }}
                            </div>

                            <span
                                class="badge {{ $stage->badgeClass() }}"
                            >
                                Cases
                            </span>

                        </div>

                    </div>

                </div>

            </a>

        </div>

    @endforeach

</div>


{{-- ========================================================= --}}
{{-- FILTERS --}}
{{-- ========================================================= --}}

<div class="card shadow-sm mb-4">

    <div class="card-body">

        <form
            method="GET"
            action="{{ route('cases.index') }}"
            class="row g-2 align-items-end"
        >

            {{-- Search --}}
            <div class="col-12 col-lg-4">

                <label
                    class="form-label small text-muted"
                >
                    Search
                </label>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control"
                    placeholder="Case number, person, location..."
                >

            </div>


            {{-- Current Stage --}}
            <div class="col-12 col-md-4 col-lg-2">

                <label
                    class="form-label small text-muted"
                >
                    Current Stage
                </label>

                <select
                    name="stage"
                    class="form-select"
                >

                    <option value="">
                        All Stages
                    </option>

                    @foreach($stages as $stage)

                        <option
                            value="{{ $stage->value }}"
                            @selected(
                                request('stage')
                                === $stage->value
                            )
                        >
                            {{ $stage->value }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- Record Status --}}
            <div class="col-12 col-md-4 col-lg-2">

                <label
                    class="form-label small text-muted"
                >
                    Record Status
                </label>

                <select
                    name="record_status"
                    class="form-select"
                >

                    <option value="">
                        All Record Statuses
                    </option>

                    @foreach(
                        $recordStatuses
                        as $recordStatus
                    )

                        <option
                            value="{{ $recordStatus->value }}"
                            @selected(
                                request('record_status')
                                === $recordStatus->value
                            )
                        >
                            {{ $recordStatus->value }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- Incident Type --}}
            <div class="col-12 col-md-4 col-lg-2">

                <label
                    class="form-label small text-muted"
                >
                    Incident Type
                </label>

                <select
                    name="incident_type_id"
                    class="form-select"
                >

                    <option value="">
                        All Types
                    </option>

                    @foreach($incidentTypes as $type)

                        <option
                            value="{{ $type->id }}"
                            @selected(
                                (string)
                                    request(
                                        'incident_type_id'
                                    )
                                ===
                                (string)
                                    $type->id
                            )
                        >
                            {{ $type->name }}
                        </option>

                    @endforeach

                </select>

            </div>


            {{-- Filter --}}
            <div class="col-6 col-lg-1 d-grid">

                <button
                    type="submit"
                    class="btn btn-outline-primary"
                >
                    Filter
                </button>

            </div>


            {{-- Clear --}}
            <div class="col-6 col-lg-1 d-grid">

                <a
                    href="{{ route('cases.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Clear
                </a>

            </div>

        </form>

    </div>

</div>


{{-- ========================================================= --}}
{{-- CASE TABLE --}}
{{-- ========================================================= --}}

<div class="card shadow-sm">

    <div
        class="card-header bg-white d-flex justify-content-between align-items-center"
    >

        <strong>
            Case Tracking
        </strong>

        <span class="text-muted small">

            {{
                number_format(
                    $cases->total()
                )
            }}

            record(s)

        </span>

    </div>


    <div class="table-responsive">

        <table
            class="table table-hover align-middle mb-0"
        >

            <thead>

                <tr>

                    <th>
                        Case No.
                    </th>

                    <th>
                        Complainant
                    </th>

                    <th>
                        Respondent
                    </th>

                    <th>
                        Current Stage
                    </th>

                    <th>
                        Record Status
                    </th>

                    <th>
                        Assigned To
                    </th>

                    <th>
                        Last Updated
                    </th>

                    <th class="text-end">
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($cases as $case)

                    @php

                        $caseStage =
                            $case->case_stage;

                        $recordStatus =
                            $case->record_status;

                    @endphp


                    <tr>

                        {{-- Case Number --}}
                        <td>

                            <div class="fw-semibold">
                                {{ $case->reference_number }}
                            </div>

                            <div class="small text-muted">

                                {{
                                    $case
                                        ->incidentType
                                        ?->name
                                    ?? '—'
                                }}

                            </div>

                        </td>


                        {{-- Complainant --}}
                        <td>

                            @forelse(
                                $case->complainants
                                as $person
                            )

                                <div>

                                    {{
                                        trim(
                                            "{$person->first_name} "
                                            . "{$person->middle_name} "
                                            . "{$person->last_name} "
                                            . "{$person->suffix}"
                                        )
                                    }}

                                </div>

                            @empty

                                —

                            @endforelse

                        </td>


                        {{-- Respondent --}}
                        <td>

                            @forelse(
                                $case->respondents
                                as $person
                            )

                                <div>

                                    {{
                                        trim(
                                            "{$person->first_name} "
                                            . "{$person->middle_name} "
                                            . "{$person->last_name} "
                                            . "{$person->suffix}"
                                        )
                                    }}

                                </div>

                            @empty

                                —

                            @endforelse

                        </td>


                        {{-- Current Stage --}}
                        <td>

                            @if($caseStage)

                                <span
                                    class="badge {{ $caseStage->badgeClass() }}"
                                >
                                    {{ $caseStage->value }}
                                </span>

                            @else

                                <span
                                    class="badge text-bg-secondary"
                                >
                                    New
                                </span>

                            @endif

                        </td>


                        {{-- Record Status --}}
                        <td>

                            @if($recordStatus)

                                <span
                                    class="badge {{ $recordStatus->badgeClass() }}"
                                >
                                    {{ $recordStatus->value }}
                                </span>

                            @else

                                <span
                                    class="badge text-bg-primary"
                                >
                                    Open
                                </span>

                            @endif

                        </td>


                        {{-- Assigned Officer --}}
                        <td>

                            {{
                                $case
                                    ->currentAssignment
                                    ?->assignedOfficer
                                    ?->name
                                ?? '—'
                            }}

                        </td>


                        {{-- Updated --}}
                        <td class="text-nowrap">

                            {{
                                $case
                                    ->updated_at
                                    ?->format(
                                        'M d, Y h:i A'
                                    )
                                ?? '—'
                            }}

                        </td>


                        {{-- Action --}}
                        <td class="text-end">

                            <a
                                href="{{
                                    route(
                                        'blotter.show',
                                        $case
                                    )
                                }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                <i
                                    class="bi bi-eye me-1"
                                ></i>

                                View / Manage

                            </a>

                        </td>

                    </tr>


                @empty

                    <tr>

                        <td
                            colspan="8"
                            class="text-center text-muted py-5"
                        >
                            No cases found for the selected filters.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    @if($cases->hasPages())

        <div class="card-footer bg-white">

            {{ $cases->links() }}

        </div>

    @endif

</div>

@endsection