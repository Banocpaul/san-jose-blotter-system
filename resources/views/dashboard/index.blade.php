@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

@php
    $currentUser = auth()->user();

    $roleSlug =
        $role
        ?? $currentUser?->role?->slug;

    $roleName =
        $currentUser?->role?->name
        ?? 'User';

    $hour = now()->hour;

    $greeting = match (true) {
        $hour < 12 => 'Good morning',
        $hour < 18 => 'Good afternoon',
        default => 'Good evening',
    };
@endphp


{{-- ========================================================= --}}
{{-- DASHBOARD HEADER --}}
{{-- ========================================================= --}}

<div class="mb-4">

    <h3 class="mb-1">
        {{ $greeting }},
        {{ $currentUser?->name ?? 'User' }}.
    </h3>

    <div class="text-muted">

        @if(
            in_array(
                $roleSlug,
                [
                    'barangay_captain',
                    'secretary'
                ],
                true
            )
        )

            Barangay San Jose case management overview.

        @elseif($roleSlug === 'staff')

            Your current complainant and blotter encoding activity.

        @elseif($roleSlug === 'councilor')

            Cases currently assigned to you for investigation.

        @elseif($roleSlug === 'lupon')

            Your assigned mediation cases and scheduled hearings.

        @else

            Barangay San Jose Blotter Management System.

        @endif

    </div>

</div>


{{-- ========================================================= --}}
{{-- BARANGAY CAPTAIN / SECRETARY --}}
{{-- ========================================================= --}}

@if(
    in_array(
        $roleSlug,
        [
            'barangay_captain',
            'secretary'
        ],
        true
    )
)

<div class="row g-3">

    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Complainant Records
                </div>

                <h2 class="mb-0">
                    {{ $totalResidents ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Total Cases
                </div>

                <h2 class="mb-0">
                    {{ $totalCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Pending
                </div>

                <h2 class="mb-0">
                    {{ $pendingCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Under Investigation
                </div>

                <h2 class="mb-0">
                    {{ $underInvestigationCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    For Mediation
                </div>

                <h2 class="mb-0">
                    {{ $forMediationCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Scheduled Hearings
                </div>

                <h2 class="mb-0">
                    {{ $scheduledHearings ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Settled Cases
                </div>

                <h2 class="mb-0">
                    {{ $settledCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- STAFF --}}
{{-- ========================================================= --}}

@elseif($roleSlug === 'staff')

<div class="row g-3">

    <div class="col-md-3">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Complainant Records
                </div>

                <h2 class="mb-0">
                    {{ $totalResidents ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-3">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Total Cases
                </div>

                <h2 class="mb-0">
                    {{ $totalCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-3">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Pending
                </div>

                <h2 class="mb-0">
                    {{ $pendingCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-3">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Under Investigation
                </div>

                <h2 class="mb-0">
                    {{ $underInvestigationCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- COUNCILOR --}}
{{-- ========================================================= --}}

@elseif($roleSlug === 'councilor')

<div class="row g-3">

    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Assigned Cases
                </div>

                <h2 class="mb-0">
                    {{ $assignedCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Pending
                </div>

                <h2 class="mb-0">
                    {{ $pendingCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Under Investigation
                </div>

                <h2 class="mb-0">
                    {{ $underInvestigationCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- LUPON --}}
{{-- ========================================================= --}}

@elseif($roleSlug === 'lupon')

<div class="row g-3">

    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Assigned Mediation Cases
                </div>

                <h2 class="mb-0">
                    {{ $assignedCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    For Mediation
                </div>

                <h2 class="mb-0">
                    {{ $forMediationCases ?? 0 }}
                </h2>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm h-100">

            <div class="card-body">

                <div class="text-muted">
                    Scheduled Hearings
                </div>

                <h2 class="mb-0">
                    {{ $scheduledHearings ?? 0 }}
                </h2>

            </div>

        </div>

    </div>

</div>

@endif


{{-- ========================================================= --}}
{{-- RECENT CASES --}}
{{-- ========================================================= --}}

<div class="card shadow-sm mt-4">

    <div class="card-header bg-white">

        <strong>
            Recent Blotter Cases
        </strong>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead>

                    <tr>

                        <th>
                            Reference
                        </th>

                        <th>
                            Incident
                        </th>

                        <th>
                            Reported
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                @forelse(($recentCases ?? collect()) as $case)

                    <tr>

                        <td>

                            <strong>
                                {{ $case->reference_number }}
                            </strong>

                        </td>


                        <td>

                            {{
                                $case->incidentType?->name
                                ?? '—'
                            }}

                        </td>


                        <td>

                            @if($case->reported_at)

                                {{
                                    $case
                                        ->reported_at
                                        ->format('M d, Y')
                                }}

                            @else

                                —

                            @endif

                        </td>


                        <td>

                            @php
                                $caseStatus =
                                    $case->status instanceof \BackedEnum
                                        ? $case->status->value
                                        : $case->status;
                            @endphp

                            <span class="badge bg-secondary">

                                {{ $caseStatus }}

                            </span>

                        </td>


                        <td>

                            <a
                                href="{{
                                    route(
                                        'blotter.show',
                                        $case
                                    )
                                }}"
                                class="btn btn-sm btn-outline-primary"
                            >
                                View
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted py-4"
                        >

                            No blotter cases available.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- UPCOMING HEARINGS --}}
{{-- Captain / Secretary / Lupon only --}}
{{-- ========================================================= --}}

@if(
    in_array(
        $roleSlug,
        [
            'barangay_captain',
            'secretary',
            'lupon'
        ],
        true
    )
)

<div class="card shadow-sm mt-4">

    <div class="card-header bg-white">

        <strong>
            Upcoming Hearings
        </strong>

    </div>


    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover mb-0">

                <thead>

                    <tr>

                        <th>
                            Case
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Time
                        </th>

                        <th>
                            Lupon Member
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>

                @forelse(
                    ($upcomingHearings ?? collect())
                    as $hearing
                )

                    <tr>

                        <td>

                            {{
                                $hearing
                                    ->blotterCase
                                    ?->reference_number
                                ?? '—'
                            }}

                        </td>


                        <td>

                            @if($hearing->scheduled_date)

                                {{
                                    \Illuminate\Support\Carbon::parse(
                                        $hearing->scheduled_date
                                    )
                                    ->format('M d, Y')
                                }}

                            @else

                                —

                            @endif

                        </td>


                        <td>

                            {{
                                $hearing->scheduled_time
                                ?? '—'
                            }}

                        </td>


                        <td>

                            {{
                                $hearing
                                    ->luponMember
                                    ?->name
                                ?? '—'
                            }}

                        </td>


                        <td>

                            <span class="badge bg-primary">

                                {{
                                    $hearing->status
                                    ?? 'Scheduled'
                                }}

                            </span>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted py-4"
                        >

                            No upcoming hearings.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endif

@endsection