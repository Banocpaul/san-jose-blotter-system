@extends('layouts.app')

@section('title', 'Blotter Records')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Blotter Records</h3>
        <div class="text-muted">
            Initial complaint and incident records linked to the People Directory.
        </div>
    </div>

    <a href="{{ route('blotter.create') }}"
       class="btn btn-primary">
        <i class="bi bi-file-earmark-plus me-1"></i> New Blotter Case
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card shadow-sm">

    <div class="card-body">

        <form method="GET"
              action="{{ route('blotter.index') }}"
              class="row g-2 mb-4">

            <div class="col-md-4">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control"
                    placeholder="Case number, person, location..."
                >
            </div>

            <div class="col-md-3">

                <select name="status"
                        class="form-select">

                    <option value="">
                        All Statuses
                    </option>

                    @foreach($statuses as $status)

                        <option
                            value="{{ $status->value }}"
                            @selected(
                                request('status') === $status->value
                            )
                        >
                            {{ $status->value }}
                        </option>

                    @endforeach

                </select>

            </div>

            <div class="col-md-3">

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

            <div class="col-md-2 d-grid">

                <button
                    type="submit"
                    class="btn btn-outline-primary"
                >
                    Search
                </button>

            </div>

        </form>

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Incident</th>
                        <th>Complainant</th>
                        <th>Respondent</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($cases as $case)

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
                                    {{ $person->first_name }}
                                    {{ $person->middle_name }}
                                    {{ $person->last_name }}
                                    {{ $person->suffix }}
                                </div>

                            @empty
                                —
                            @endforelse

                        </td>

                        <td>

                            @forelse($case->respondents as $person)

                                <div>
                                    {{ $person->first_name }}
                                    {{ $person->middle_name }}
                                    {{ $person->last_name }}
                                    {{ $person->suffix }}
                                </div>

                            @empty
                                —
                            @endforelse

                        </td>

                        <td>

                            {{
                                $case->incident_date
                                    ? $case->incident_date->format('M d, Y')
                                    : '—'
                            }}

                        </td>

                        <td>

                            @php
                                $statusClass = match($case->status->value) {
                                    'Pending' => 'text-bg-warning',
                                    'Under Investigation' => 'text-bg-primary',
                                    'For Mediation' => 'text-bg-info',
                                    'Settled' => 'text-bg-success',
                                    'Resolved' => 'text-bg-success',
                                    'Referred' => 'text-bg-dark',
                                    'Dismissed' => 'text-bg-secondary',
                                    default => 'text-bg-secondary',
                                };
                            @endphp

                            <span class="badge {{ $statusClass }}">
                                {{ $case->status->value }}
                            </span>

                        </td>

                        <td>

                            <a
                                href="{{ route('blotter.show', $case) }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                View
                            </a>

                            <a
                                href="{{ route('blotter.edit', $case) }}"
                                class="btn btn-sm btn-outline-secondary"
                            >
                                Edit
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No blotter records found.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

        <div class="mt-3">
            {{ $cases->links() }}
        </div>

    </div>

</div>

@endsection