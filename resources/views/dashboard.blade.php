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

    $closedCases =
        ($settledCases ?? 0)
        +
        ($resolvedCases ?? 0)
        +
        ($referredCases ?? 0)
        +
        ($dismissedCases ?? 0);
@endphp


@push('styles')

<style>
    :root {
        --dash-ink: #0a0c10;
        --dash-ink-soft: #151821;
        --dash-blue: #2563eb;
        --dash-blue-dark: #1746b8;
        --dash-blue-soft: #eaf1ff;
        --dash-white: #ffffff;
        --dash-canvas: #f5f8fc;
        --dash-line: #dbe2ec;
        --dash-muted: #667085;
        --dash-radius: 22px;
        --dash-radius-sm: 14px;
        --dash-shadow: 0 18px 45px rgba(15, 23, 42, .075);
        --dash-shadow-soft: 0 8px 24px rgba(15, 23, 42, .055);
    }

    /*
    |--------------------------------------------------------------------------
    | Hero / Intro
    |--------------------------------------------------------------------------
    */

    .dashboard-intro {
        position: relative;
        overflow: hidden;

        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            minmax(210px, 290px);

        gap: 22px;

        margin-bottom: 24px;
        padding: 32px;

        border:
            1px solid var(--dash-ink);

        border-radius:
            var(--dash-radius);

        background:
            var(--dash-white);

        box-shadow:
            var(--dash-shadow);

        isolation: isolate;
    }

    .dashboard-intro::before {
        content: "";

        position: absolute;
        width: 240px;
        height: 240px;

        right: 180px;
        top: -120px;

        border-radius: 999px;

        background:
            rgba(37, 99, 235, .11);

        border:
            1px solid rgba(37, 99, 235, .26);

        z-index: -1;
    }

    .dashboard-intro::after {
        content: "";

        position: absolute;
        width: 92px;
        height: 92px;

        right: 290px;
        bottom: -46px;

        transform: rotate(18deg);

        border-radius: 24px;

        background:
            var(--dash-blue);

        opacity: .10;

        z-index: -1;
    }

    .dashboard-intro-kicker {
        display: inline-flex;
        align-items: center;

        gap: 7px;

        margin-bottom: 12px;

        color:
            var(--dash-blue-dark);

        font-size: 9px;
        font-weight: 700;

        text-transform: uppercase;
        letter-spacing: .17em;
    }

    .dashboard-intro-kicker::before {
        content: "";

        width: 26px;
        height: 2px;

        border-radius: 999px;

        background:
            var(--dash-blue);
    }

    .dashboard-intro h1 {
        max-width: 760px;

        margin: 0;

        color:
            var(--dash-ink);

        font-size:
            clamp(
                30px,
                4vw,
                52px
            );

        font-weight: 700;

        line-height: .98;

        letter-spacing: -.06em;
    }

    .dashboard-intro-text {
        max-width: 650px;

        margin:
            16px
            0
            0;

        color:
            var(--dash-muted);

        font-size: 12px;
        line-height: 1.75;
    }

    .dashboard-date {
        position: relative;

        min-height: 150px;

        display: flex;
        flex-direction: column;
        justify-content: space-between;

        padding: 20px;

        border:
            1px solid var(--dash-ink);

        border-radius:
            18px;

        color:
            rgba(255, 255, 255, .72);

        background:
            var(--dash-ink);

        font-size: 10px;
        font-weight: 600;

        text-align: left;

        box-shadow:
            0 14px 34px
            rgba(10, 12, 16, .16);
    }

    .dashboard-date::before {
        content: "SJ";

        width: 38px;
        height: 38px;

        display: grid;
        place-items: center;

        border:
            1px solid rgba(255, 255, 255, .14);

        border-radius: 12px;

        color:
            #ffffff;

        background:
            var(--dash-blue);

        font-size: 10px;
        font-weight: 700;

        letter-spacing: .08em;
    }

    .dashboard-date strong {
        display: block;

        margin-top: 5px;

        color:
            #ffffff;

        font-size: 17px;
        font-weight: 600;

        line-height: 1.1;

        letter-spacing: -.04em;
    }

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    .metric-grid {
        margin-bottom: 30px;
    }

    .metric-card-lux {
        position: relative;
        overflow: hidden;

        height: 100%;

        padding: 20px;

        border:
            1px solid var(--dash-line);

        border-radius:
            18px;

        color:
            var(--dash-ink);

        background:
            var(--dash-white);

        box-shadow:
            var(--dash-shadow-soft);

        transition:
            transform .18s ease,
            border-color .18s ease,
            box-shadow .18s ease;
    }

    .metric-grid > div:nth-child(1) .metric-card-lux {
        color: #ffffff;

        border-color:
            var(--dash-blue);

        background:
            var(--dash-blue);
    }

    .metric-grid > div:nth-child(2) .metric-card-lux {
        color: #ffffff;

        border-color:
            var(--dash-ink);

        background:
            var(--dash-ink);
    }

    .metric-card-lux:hover {
        transform:
            translateY(-3px);

        border-color:
            #aeb9c8;

        box-shadow:
            var(--dash-shadow);
    }

    .metric-card-lux::after {
        content: "";

        position: absolute;
        right: -16px;
        bottom: -20px;

        width: 74px;
        height: 74px;

        border:
            1px solid rgba(37, 99, 235, .18);

        border-radius:
            22px;

        transform:
            rotate(19deg);

        pointer-events: none;
    }

    .metric-grid > div:nth-child(1) .metric-card-lux::after,
    .metric-grid > div:nth-child(2) .metric-card-lux::after {
        border-color:
            rgba(255, 255, 255, .16);
    }

    .metric-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;

        gap: 12px;

        margin-bottom: 20px;
    }

    .metric-label-lux {
        color:
            var(--dash-muted);

        font-size: 9px;
        font-weight: 700;

        text-transform: uppercase;
        letter-spacing: .11em;
    }

    .metric-grid > div:nth-child(1) .metric-label-lux,
    .metric-grid > div:nth-child(2) .metric-label-lux {
        color:
            rgba(255, 255, 255, .72);
    }

    .metric-icon-lux {
        width: 34px;
        height: 34px;

        display: grid;
        place-items: center;

        flex: 0 0 34px;

        border:
            1px solid #dbe4f4;

        border-radius: 11px;

        color:
            var(--dash-blue);

        background:
            var(--dash-blue-soft);

        font-size: 14px;
    }

    .metric-grid > div:nth-child(1) .metric-icon-lux,
    .metric-grid > div:nth-child(2) .metric-icon-lux {
        color:
            #ffffff;

        border-color:
            rgba(255, 255, 255, .16);

        background:
            rgba(255, 255, 255, .08);
    }

    .metric-number {
        color:
            var(--dash-ink);

        font-size:
            clamp(
                32px,
                4vw,
                46px
            );

        font-weight: 700;

        line-height: .95;

        letter-spacing: -.06em;
    }

    .metric-grid > div:nth-child(1) .metric-number,
    .metric-grid > div:nth-child(2) .metric-number {
        color:
            #ffffff;
    }

    .metric-note {
        min-height: 18px;

        margin-top: 13px;

        color:
            #9299a5;

        font-size: 9px;
    }

    .metric-note a {
        color:
            var(--dash-blue-dark);

        text-decoration: none;

        font-weight: 700;
    }

    .metric-grid > div:nth-child(1) .metric-note,
    .metric-grid > div:nth-child(2) .metric-note,
    .metric-grid > div:nth-child(1) .metric-note a,
    .metric-grid > div:nth-child(2) .metric-note a {
        color:
            rgba(255, 255, 255, .82);
    }

    .metric-note a:hover {
        text-decoration: underline;
    }

    /*
    |--------------------------------------------------------------------------
    | Section Heading
    |--------------------------------------------------------------------------
    */

    .section-heading-lux {
        display: flex;
        align-items: end;
        justify-content: space-between;

        gap: 16px;

        margin-bottom: 14px;
    }

    .section-heading-lux h2 {
        margin: 0;

        color:
            var(--dash-ink);

        font-size: 15px;
        font-weight: 700;

        letter-spacing: -.03em;
    }

    .section-heading-lux p {
        margin:
            4px
            0
            0;

        color:
            #8b93a0;

        font-size: 9px;
    }

    /*
    |--------------------------------------------------------------------------
    | Quick Actions
    |--------------------------------------------------------------------------
    */

    .quick-actions-lux {
        margin-bottom: 30px;
    }

    .quick-action-lux {
        position: relative;
        overflow: hidden;

        height: 100%;

        display: flex;
        align-items: center;

        gap: 14px;

        padding: 15px;

        border:
            1px solid var(--dash-ink);

        border-radius:
            15px;

        color:
            var(--dash-ink);

        background:
            #ffffff;

        text-decoration: none;

        box-shadow:
            0 5px 16px
            rgba(15, 23, 42, .035);

        transition:
            transform .18s ease,
            box-shadow .18s ease,
            background-color .18s ease,
            color .18s ease;
    }

    .quick-actions-lux > div:nth-child(2) .quick-action-lux {
        color:
            #ffffff;

        background:
            var(--dash-blue);
    }

    .quick-actions-lux > div:nth-child(4) .quick-action-lux {
        color:
            #ffffff;

        background:
            var(--dash-ink);
    }

    .quick-action-lux:hover {
        color:
            var(--dash-ink);

        transform:
            translateY(-3px);

        box-shadow:
            var(--dash-shadow);
    }

    .quick-actions-lux > div:nth-child(2) .quick-action-lux:hover,
    .quick-actions-lux > div:nth-child(4) .quick-action-lux:hover {
        color:
            #ffffff;
    }

    .quick-action-icon-lux {
        width: 39px;
        height: 39px;

        display: grid;
        place-items: center;

        flex: 0 0 39px;

        border:
            1px solid #cbd8f4;

        border-radius:
            12px;

        color:
            var(--dash-blue-dark);

        background:
            var(--dash-blue-soft);

        font-size: 15px;
    }

    .quick-actions-lux > div:nth-child(2) .quick-action-icon-lux,
    .quick-actions-lux > div:nth-child(4) .quick-action-icon-lux {
        color:
            #ffffff;

        border-color:
            rgba(255, 255, 255, .18);

        background:
            rgba(255, 255, 255, .08);
    }

    .quick-action-title-lux {
        color:
            inherit;

        font-size: 11px;
        font-weight: 700;
    }

    .quick-action-subtitle-lux {
        margin-top: 3px;

        color:
            #9199a7;

        font-size: 8px;
    }

    .quick-actions-lux > div:nth-child(2) .quick-action-subtitle-lux,
    .quick-actions-lux > div:nth-child(4) .quick-action-subtitle-lux {
        color:
            rgba(255, 255, 255, .72);
    }

    /*
    |--------------------------------------------------------------------------
    | Main Panels
    |--------------------------------------------------------------------------
    */

    .lux-panel {
        height: 100%;

        overflow: hidden;

        border:
            1px solid var(--dash-line);

        border-radius:
            20px;

        background:
            #ffffff;

        box-shadow:
            var(--dash-shadow-soft);
    }

    .lux-panel-header {
        min-height: 66px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 14px;

        padding:
            17px
            19px;

        border-bottom:
            1px solid #e7ecf3;

        background:
            linear-gradient(
                180deg,
                #ffffff,
                #fbfcff
            );
    }

    .lux-panel-title {
        color:
            var(--dash-ink);

        font-size: 12px;
        font-weight: 700;

        letter-spacing: -.02em;
    }

    .lux-panel-subtitle {
        margin-top: 4px;

        color:
            #9198a5;

        font-size: 8px;
    }

    .lux-panel-action {
        display: inline-flex;
        align-items: center;

        min-height: 32px;

        padding:
            0
            11px;

        border:
            1px solid var(--dash-ink);

        border-radius:
            999px;

        color:
            var(--dash-ink);

        background:
            #ffffff;

        text-decoration: none;

        font-size: 9px;
        font-weight: 700;
    }

    .lux-panel-action:hover {
        color:
            #ffffff;

        background:
            var(--dash-ink);
    }

    .case-ref-lux {
        color:
            var(--dash-blue-dark);

        font-weight: 700;
    }

    .party-line {
        color:
            #545b67;

        font-size: 10px;
    }

    .party-line small {
        display: block;

        margin-top: 2px;

        color:
            #9da4af;

        font-size: 8px;
    }

    /*
    |--------------------------------------------------------------------------
    | Hearings
    |--------------------------------------------------------------------------
    */

    .hearing-list {
        padding:
            3px
            18px;
    }

    .hearing-row {
        display: flex;
        align-items: center;

        gap: 13px;

        padding:
            15px
            0;

        border-bottom:
            1px solid #edf0f5;
    }

    .hearing-row:last-child {
        border-bottom: 0;
    }

    .hearing-date-lux {
        width: 46px;
        height: 50px;

        display: flex;
        flex-direction: column;
        justify-content: center;

        flex: 0 0 46px;

        border:
            1px solid #c6d6fb;

        border-radius: 12px;

        text-align: center;

        background:
            var(--dash-blue-soft);
    }

    .hearing-date-lux span {
        color:
            var(--dash-blue-dark);

        font-size: 7px;
        font-weight: 700;

        text-transform: uppercase;
        letter-spacing: .1em;
    }

    .hearing-date-lux strong {
        margin-top: 1px;

        color:
            var(--dash-ink);

        font-size: 17px;
        font-weight: 700;
    }

    .hearing-info {
        flex: 1;
        min-width: 0;
    }

    .hearing-ref-lux {
        color:
            var(--dash-ink);

        font-size: 10px;
        font-weight: 700;
    }

    .hearing-meta-lux {
        margin-top: 4px;

        color:
            #8b93a0;

        font-size: 8px;

        line-height: 1.6;
    }

    .hearing-arrow {
        width: 31px;
        height: 31px;

        display: grid;
        place-items: center;

        flex: 0 0 31px;

        border:
            1px solid var(--dash-ink);

        border-radius: 10px;

        color:
            var(--dash-ink);

        background:
            #ffffff;

        text-decoration: none;

        transition:
            color .16s ease,
            border-color .16s ease,
            background-color .16s ease,
            transform .16s ease;
    }

    .hearing-arrow:hover {
        color:
            #ffffff;

        border-color:
            var(--dash-blue);

        background:
            var(--dash-blue);

        transform:
            translateX(2px);
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    .lux-panel .table thead th {
        background:
            #f6f8fc;

        color:
            #737b88;
    }

    .lux-panel .table tbody tr:hover {
        background:
            #f6f9ff;
    }

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    .empty-state-lux {
        padding:
            42px
            18px;

        text-align: center;

        color:
            #9299a5;
    }

    .empty-state-lux i {
        display: block;

        margin-bottom: 10px;

        color:
            var(--dash-blue);

        font-size: 22px;
    }

    .empty-state-lux strong {
        display: block;

        color:
            #4a505a;

        font-size: 10px;
        font-weight: 700;
    }

    .empty-state-lux span {
        display: block;

        margin-top: 4px;

        font-size: 8px;
    }

    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (
        max-width: 991.98px
    ) {
        .dashboard-intro {
            grid-template-columns: 1fr;
        }

        .dashboard-date {
            min-height: 118px;
        }
    }

    @media (
        max-width: 767.98px
    ) {
        .dashboard-intro {
            padding: 24px;
        }

        .dashboard-intro h1 {
            font-size: 34px;
        }

        .dashboard-date {
            margin-top: 0;
        }
    }

    @media (
        max-width: 575.98px
    ) {
        .dashboard-intro {
            padding: 20px;

            border-radius: 18px;
        }

        .dashboard-intro h1 {
            font-size: 30px;
        }

        .metric-card-lux {
            padding: 17px;
        }
    }
</style>

@endpush


{{-- ========================================================= --}}
{{-- INTRO --}}
{{-- ========================================================= --}}

<div class="dashboard-intro">

    <div>

        <div class="dashboard-intro-kicker">
            Barangay operations • live overview
        </div>

        <h1>
            {{ $greeting }},
            {{ $currentUser?->name ?? 'User' }}.
        </h1>

        <p class="dashboard-intro-text">

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

                A concise view of residents, cases, investigations
                and mediation activity across Barangay San Jose.

            @elseif($roleSlug === 'staff')

                Your current resident and blotter encoding activity.

            @elseif($roleSlug === 'councilor')

                Cases currently assigned to you for investigation.

            @elseif($roleSlug === 'lupon')

                Your assigned mediation cases and scheduled hearings.

            @else

                Barangay San Jose records and case management.

            @endif

        </p>

    </div>


    <div class="dashboard-date">

        Today

        <strong>
            {{ now()->format('F d, Y') }}
        </strong>

    </div>

</div>


{{-- ========================================================= --}}
{{-- CAPTAIN / SECRETARY --}}
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

    <div class="row g-3 metric-grid">

        @php
            $metrics = [
                [
                    'label' => 'Active Residents',
                    'value' => $totalResidents ?? 0,
                    'icon' => 'bi-people',
                    'route' => route('residents.index'),
                    'note' => 'View resident registry',
                ],
                [
                    'label' => 'Total Cases',
                    'value' => $totalCases ?? 0,
                    'icon' => 'bi-folder2',
                    'route' => route('blotter.index'),
                    'note' => 'Open case registry',
                ],
                [
                    'label' => 'Pending',
                    'value' => $pendingCases ?? 0,
                    'icon' => 'bi-hourglass-split',
                    'route' => route('blotter.index', ['status' => 'Pending']),
                    'note' => 'Review pending cases',
                ],
                [
                    'label' => 'Under Investigation',
                    'value' => $underInvestigationCases ?? 0,
                    'icon' => 'bi-search',
                    'route' => route('blotter.index', ['status' => 'Under Investigation']),
                    'note' => 'View investigations',
                ],
                [
                    'label' => 'For Mediation',
                    'value' => $forMediationCases ?? 0,
                    'icon' => 'bi-chat-square-text',
                    'route' => route('blotter.index', ['status' => 'For Mediation']),
                    'note' => 'View mediation queue',
                ],
                [
                    'label' => 'Scheduled Hearings',
                    'value' => $scheduledHearings ?? 0,
                    'icon' => 'bi-calendar3',
                    'route' => null,
                    'note' => 'Active mediation schedules',
                ],
                [
                    'label' => 'Settled',
                    'value' => $settledCases ?? 0,
                    'icon' => 'bi-check2-circle',
                    'route' => route('blotter.index', ['status' => 'Settled']),
                    'note' => 'View settled cases',
                ],
                [
                    'label' => 'Closed Cases',
                    'value' => $closedCases,
                    'icon' => 'bi-archive',
                    'route' => null,
                    'note' => 'Settled, resolved, referred, dismissed',
                ],
            ];
        @endphp

        @foreach($metrics as $metric)

            <div class="col-sm-6 col-xl-3">

                <div class="metric-card-lux">

                    <div class="metric-top">

                        <div class="metric-label-lux">
                            {{ $metric['label'] }}
                        </div>

                        <div class="metric-icon-lux">
                            <i class="bi {{ $metric['icon'] }}"></i>
                        </div>

                    </div>

                    <div class="metric-number">
                        {{ number_format($metric['value']) }}
                    </div>

                    <div class="metric-note">

                        @if($metric['route'])

                            <a href="{{ $metric['route'] }}">
                                {{ $metric['note'] }}
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        @else

                            {{ $metric['note'] }}

                        @endif

                    </div>

                </div>

            </div>

        @endforeach

    </div>

@endif


{{-- ========================================================= --}}
{{-- STAFF --}}
{{-- ========================================================= --}}

@if($roleSlug === 'staff')

    <div class="row g-3 metric-grid">

        @foreach([
            ['Active Residents', $totalResidents ?? 0, 'bi-people'],
            ['Total Cases', $totalCases ?? 0, 'bi-folder2'],
            ['Pending', $pendingCases ?? 0, 'bi-hourglass-split'],
            ['Under Investigation', $underInvestigationCases ?? 0, 'bi-search'],
        ] as [$label, $value, $icon])

            <div class="col-sm-6 col-xl-3">

                <div class="metric-card-lux">

                    <div class="metric-top">

                        <div class="metric-label-lux">
                            {{ $label }}
                        </div>

                        <div class="metric-icon-lux">
                            <i class="bi {{ $icon }}"></i>
                        </div>

                    </div>

                    <div class="metric-number">
                        {{ number_format($value) }}
                    </div>

                </div>

            </div>

        @endforeach

    </div>

@endif


{{-- ========================================================= --}}
{{-- COUNCILOR --}}
{{-- ========================================================= --}}

@if($roleSlug === 'councilor')

    <div class="row g-3 metric-grid">

        @foreach([
            ['My Active Cases', $assignedCases ?? 0, 'bi-briefcase'],
            ['Under Investigation', $underInvestigationCases ?? 0, 'bi-search'],
            ['Pending', $pendingCases ?? 0, 'bi-hourglass-split'],
        ] as [$label, $value, $icon])

            <div class="col-md-4">

                <div class="metric-card-lux">

                    <div class="metric-top">

                        <div class="metric-label-lux">
                            {{ $label }}
                        </div>

                        <div class="metric-icon-lux">
                            <i class="bi {{ $icon }}"></i>
                        </div>

                    </div>

                    <div class="metric-number">
                        {{ number_format($value) }}
                    </div>

                </div>

            </div>

        @endforeach

    </div>

@endif


{{-- ========================================================= --}}
{{-- LUPON --}}
{{-- ========================================================= --}}

@if($roleSlug === 'lupon')

    <div class="row g-3 metric-grid">

        @foreach([
            ['My Mediation Cases', $assignedCases ?? 0, 'bi-folder2'],
            ['For Mediation', $forMediationCases ?? 0, 'bi-chat-square-text'],
            ['Scheduled Hearings', $scheduledHearings ?? 0, 'bi-calendar3'],
        ] as [$label, $value, $icon])

            <div class="col-md-4">

                <div class="metric-card-lux">

                    <div class="metric-top">

                        <div class="metric-label-lux">
                            {{ $label }}
                        </div>

                        <div class="metric-icon-lux">
                            <i class="bi {{ $icon }}"></i>
                        </div>

                    </div>

                    <div class="metric-number">
                        {{ number_format($value) }}
                    </div>

                </div>

            </div>

        @endforeach

    </div>

@endif


{{-- ========================================================= --}}
{{-- QUICK ACTIONS --}}
{{-- ========================================================= --}}

@if(
    in_array(
        $roleSlug,
        [
            'barangay_captain',
            'secretary',
            'staff'
        ],
        true
    )
)

    <div class="section-heading-lux">

        <div>

            <h2>
                Quick actions
            </h2>

            <p>
                Frequently used records and case functions.
            </p>

        </div>

    </div>


    <div class="row g-3 quick-actions-lux">

        <div class="col-md-6 col-xl-3">

            <a
                href="{{ route('residents.create') }}"
                class="quick-action-lux"
            >

                <div class="quick-action-icon-lux">
                    <i class="bi bi-person-plus"></i>
                </div>

                <div>

                    <div class="quick-action-title-lux">
                        Add resident
                    </div>

                    <div class="quick-action-subtitle-lux">
                        Create a resident record
                    </div>

                </div>

            </a>

        </div>


        <div class="col-md-6 col-xl-3">

            <a
                href="{{ route('blotter.create') }}"
                class="quick-action-lux"
            >

                <div class="quick-action-icon-lux">
                    <i class="bi bi-file-earmark-plus"></i>
                </div>

                <div>

                    <div class="quick-action-title-lux">
                        New case
                    </div>

                    <div class="quick-action-subtitle-lux">
                        Encode a blotter complaint
                    </div>

                </div>

            </a>

        </div>


        <div class="col-md-6 col-xl-3">

            <a
                href="{{ route('blotter.index') }}"
                class="quick-action-lux"
            >

                <div class="quick-action-icon-lux">
                    <i class="bi bi-folder2"></i>
                </div>

                <div>

                    <div class="quick-action-title-lux">
                        Case registry
                    </div>

                    <div class="quick-action-subtitle-lux">
                        Review existing cases
                    </div>

                </div>

            </a>

        </div>


        @if($roleSlug === 'barangay_captain')

            <div class="col-md-6 col-xl-3">

                <a
                    href="{{ route('admin.audit.index') }}"
                    class="quick-action-lux"
                >

                    <div class="quick-action-icon-lux">
                        <i class="bi bi-clock-history"></i>
                    </div>

                    <div>

                        <div class="quick-action-title-lux">
                            Audit trail
                        </div>

                        <div class="quick-action-subtitle-lux">
                            Review system activity
                        </div>

                    </div>

                </a>

            </div>

        @endif

    </div>

@endif


{{-- ========================================================= --}}
{{-- LOWER CONTENT --}}
{{-- ========================================================= --}}

<div class="row g-4">

    <div
        class="{{
            in_array(
                $roleSlug,
                [
                    'barangay_captain',
                    'secretary',
                    'lupon'
                ],
                true
            )
                ? 'col-xl-8'
                : 'col-12'
        }}"
    >

        <div class="lux-panel">

            <div class="lux-panel-header">

                <div>

                    <div class="lux-panel-title">

                        @if($roleSlug === 'councilor')

                            Recent assigned cases

                        @elseif($roleSlug === 'lupon')

                            Recent mediation cases

                        @else

                            Recent blotter cases

                        @endif

                    </div>

                    <div class="lux-panel-subtitle">
                        Latest records in your current view
                    </div>

                </div>

                <a
                    href="{{ route('blotter.index') }}"
                    class="lux-panel-action"
                >
                    View all
                </a>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th>Reference</th>
                            <th>Incident</th>
                            <th>Parties</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>

                    </thead>

                    <tbody>

                        @forelse(
                            $recentCases ?? collect()
                            as $case
                        )

                            @php
                                $caseStatus =
                                    $case->status instanceof
                                    \App\Enums\CaseStatus
                                        ? $case->status->value
                                        : $case->status;

                                $statusClass =
                                    match($caseStatus) {
                                        'Pending'
                                            => 'text-bg-warning',

                                        'Under Investigation'
                                            => 'text-bg-primary',

                                        'For Mediation'
                                            => 'text-bg-info',

                                        'Settled',
                                        'Resolved'
                                            => 'text-bg-success',

                                        'Referred'
                                            => 'text-bg-dark',

                                        'Dismissed'
                                            => 'text-bg-secondary',

                                        default
                                            => 'text-bg-secondary',
                                    };

                                $complainant =
                                    $case
                                        ->complainants
                                        ->first();

                                $respondent =
                                    $case
                                        ->respondents
                                        ->first();
                            @endphp


                            <tr>

                                <td>

                                    <span class="case-ref-lux">
                                        {{ $case->reference_number }}
                                    </span>

                                </td>


                                <td>

                                    {{
                                        $case
                                            ->incidentType
                                            ?->name
                                        ?? '—'
                                    }}

                                </td>


                                <td>

                                    <div class="party-line">

                                        {{
                                            $complainant
                                                ? trim(
                                                    $complainant->first_name
                                                    . ' '
                                                    . $complainant->last_name
                                                )
                                                : '—'
                                        }}

                                        <small>

                                            vs.
                                            {{
                                                $respondent
                                                    ? trim(
                                                        $respondent->first_name
                                                        . ' '
                                                        . $respondent->last_name
                                                    )
                                                    : '—'
                                            }}

                                        </small>

                                    </div>

                                </td>


                                <td>

                                    <span
                                        class="badge {{ $statusClass }}"
                                    >
                                        {{ $caseStatus }}
                                    </span>

                                </td>


                                <td class="text-nowrap">

                                    {{
                                        $case
                                            ->reported_at
                                            ?->format('M d, Y')
                                        ?? '—'
                                    }}

                                </td>


                                <td class="text-end">

                                    <a
                                        href="{{
                                            route(
                                                'blotter.show',
                                                $case
                                            )
                                        }}"
                                        class="hearing-arrow"
                                    >
                                        <i class="bi bi-arrow-right"></i>
                                    </a>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="6">

                                    <div class="empty-state-lux">

                                        <i class="bi bi-folder2-open"></i>

                                        <strong>
                                            No case activity yet
                                        </strong>

                                        <span>
                                            Recent records will appear here.
                                        </span>

                                    </div>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>


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

        <div class="col-xl-4">

            <div class="lux-panel">

                <div class="lux-panel-header">

                    <div>

                        <div class="lux-panel-title">
                            Upcoming hearings
                        </div>

                        <div class="lux-panel-subtitle">
                            Scheduled mediation sessions
                        </div>

                    </div>

                    <i
                        class="bi bi-calendar3"
                        style="color:#2563eb;"
                    ></i>

                </div>


                <div class="hearing-list">

                    @forelse(
                        $upcomingHearings ?? collect()
                        as $hearing
                    )

                        <div class="hearing-row">

                            <div class="hearing-date-lux">

                                <span>
                                    {{
                                        $hearing
                                            ->scheduled_date
                                            ?->format('M')
                                        ?? '---'
                                    }}
                                </span>

                                <strong>
                                    {{
                                        $hearing
                                            ->scheduled_date
                                            ?->format('d')
                                        ?? '--'
                                    }}
                                </strong>

                            </div>


                            <div class="hearing-info">

                                <div class="hearing-ref-lux">

                                    {{
                                        $hearing
                                            ->blotterCase
                                            ?->reference_number
                                        ?? 'Unknown case'
                                    }}

                                </div>

                                <div class="hearing-meta-lux">

                                    Hearing
                                    #{{ $hearing->hearing_number }}

                                    @if($hearing->scheduled_time)

                                        ·

                                        {{
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    $hearing
                                                        ->scheduled_time
                                                )
                                            )
                                        }}

                                    @endif

                                    <br>

                                    {{
                                        $hearing->venue
                                        ?? 'No venue'
                                    }}

                                </div>

                            </div>


                            @if($hearing->blotterCase)

                                <a
                                    href="{{
                                        route(
                                            'blotter.show',
                                            $hearing->blotterCase
                                        )
                                    }}"
                                    class="hearing-arrow"
                                >
                                    <i class="bi bi-arrow-right"></i>
                                </a>

                            @endif

                        </div>

                    @empty

                        <div class="empty-state-lux">

                            <i class="bi bi-calendar2-check"></i>

                            <strong>
                                No upcoming hearings
                            </strong>

                            <span>
                                Scheduled sessions will appear here.
                            </span>

                        </div>

                    @endforelse

                </div>

            </div>

        </div>

    @endif

</div>

@endsection
