@extends('layouts.app')

@section('title', 'Blotter Records')
@section('page-title', 'Blotter Records')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <h3 class="mb-1">
            Blotter Records
        </h3>

        <div class="text-muted">
            Read-only registry of recorded complaints and incidents.
            Existing records are managed through Case Management.
        </div>

    </div>


    <div class="d-flex flex-wrap align-items-center gap-2">

        @can('create', \App\Models\BlotterCase::class)

            <a
                href="{{ route('blotter.create') }}"
                class="btn btn-primary"
            >
                <i class="bi bi-file-earmark-plus me-1"></i>
                Add Blotter Record
            </a>

        @endcan


        <span class="badge text-bg-light border px-3 py-2">
            <i class="bi bi-eye me-1"></i>
            Existing Records: View Only
        </span>

    </div>

</div>


@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


<div class="card shadow-sm blotter-search-card">

    <div class="card-body">

        <form
            method="GET"
            action="{{ route('blotter.index') }}"
            class="row g-2 mb-4 align-items-end"
        >

            <div class="col-12 col-lg-6 position-relative" data-blotter-search>

                <label class="form-label small text-muted" for="blotter_search">
                    Search
                </label>

                <input
                    id="blotter_search"
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control"
                    placeholder="Case number, person, location..."
                    autocomplete="off"
                    aria-controls="blotter_search_results"
                    aria-expanded="false"
                    data-blotter-search-input
                >

                <div
                    id="blotter_search_results"
                    class="list-group shadow-sm d-none"
                    aria-label="Matching blotter records"
                    data-blotter-search-results
                ></div>

            </div>


            <div class="col-12 col-md-6 col-lg-3">

                <label class="form-label small text-muted">
                    Incident Type
                </label>

                <select
                    name="incident_type_id"
                    class="form-select"
                >

                    <option value="">
                        All Incident Types
                    </option>

                    @foreach($incidentTypes as $type)

                        <option
                            value="{{ $type->id }}"
                            @selected(
                                request('incident_type_id') == $type->id
                            )
                        >
                            {{ $type->name }}
                        </option>

                    @endforeach

                </select>

            </div>


            <div class="col-6 col-lg-2 d-grid">

                <button
                    type="submit"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-search me-1"></i>
                    Search
                </button>

            </div>


            <div class="col-6 col-lg-1 d-grid">

                <a
                    href="{{ route('blotter.index') }}"
                    class="btn btn-outline-secondary"
                >
                    Clear
                </a>

            </div>

        </form>


        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead>

                    <tr>
                        <th>Reference</th>
                        <th>Incident</th>
                        <th>Complainant</th>
                        <th>Respondent</th>
                        <th>Date</th>
                        <th>Current Stage</th>
                        <th>Case Status</th>
                        <th class="text-end">Action</th>
                    </tr>

                </thead>


                <tbody>

                    @forelse($cases as $case)

                        @php
                            $caseStage = $case->case_stage;
                            $recordStatus = $case->record_status;

                            $stageLabel =
                                $caseStage instanceof \App\Enums\CaseStage
                                    ? $caseStage->value
                                    : ($caseStage ?? 'New');

                            $stageClass =
                                $caseStage instanceof \App\Enums\CaseStage
                                    ? $caseStage->badgeClass()
                                    : 'text-bg-secondary';

                            $recordStatusLabel =
                                $recordStatus instanceof \App\Enums\RecordStatus
                                    ? $recordStatus->value
                                    : ($recordStatus ?? 'Open');

                            $recordStatusClass =
                                $recordStatus instanceof \App\Enums\RecordStatus
                                    ? $recordStatus->badgeClass()
                                    : 'text-bg-primary';
                        @endphp


                        <tr>

                            <td>

                                <strong>
                                    {{ $case->reference_number }}
                                </strong>

                            </td>


                            <td>
                                {{ $case->incidentType?->name ?? '—' }}
                            </td>


                            <td>

                                @forelse($case->complainants as $person)

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


                            <td>

                                @forelse($case->respondents as $person)

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


                            <td class="text-nowrap">

                                {{
                                    $case->incident_date
                                        ?->format('M d, Y')
                                    ?? '—'
                                }}

                            </td>


                            <td>

                                <span class="badge {{ $stageClass }}">
                                    {{ $stageLabel }}
                                </span>

                            </td>


                            <td>

                                <span class="badge {{ $recordStatusClass }}">
                                    {{ $recordStatusLabel }}
                                </span>

                            </td>


                            <td class="text-end">

                                <a
                                    href="{{ route('blotter.show', $case) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    View
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="text-center text-muted py-5"
                            >
                                No blotter records found.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        @if($cases->hasPages())

            <div class="card-footer bg-white px-0 pb-0 mt-3">

                {{ $cases->links() }}

            </div>

        @endif

    </div>

</div>

@endsection
