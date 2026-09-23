<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#0a0b0f"
    >

    <title>
        @yield(
            'title',
            'Barangay San Jose Blotter Management System'
        )
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --sidebar-width: 276px;

            --ink: #090b10;
            --ink-soft: #171a21;
            --ink-muted: #667085;

            --canvas: #f4f7fb;
            --surface: #ffffff;
            --surface-soft: #f7f9fc;

            --line: #d9dee8;
            --line-strong: #101218;

            --accent: #2563eb;
            --accent-dark: #1746b8;
            --accent-soft: #e8f0ff;

            --danger: #c23b45;
            --success: #16794f;
            --warning: #b26a00;

            --shadow:
                0 18px 45px rgba(15, 23, 42, .075);

            --shadow-soft:
                0 7px 20px rgba(15, 23, 42, .055);

            --radius: 18px;
            --radius-sm: 12px;

            --ease:
                cubic-bezier(.22, .61, .36, 1);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;

            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(37, 99, 235, .08),
                    transparent 26rem
                ),
                linear-gradient(
                    180deg,
                    #f8faff 0%,
                    var(--canvas) 100%
                );

            color: var(--ink);

            font-family:
                "Space Grotesk",
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            font-size: 14px;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        ::selection {
            color: #ffffff;
            background: var(--accent);
        }

        a,
        button,
        input,
        select,
        textarea {
            -webkit-tap-highlight-color: transparent;
        }

        /*
        |--------------------------------------------------------------------------
        | Progress Bar
        |--------------------------------------------------------------------------
        */

        .page-progress {
            position: fixed;
            top: 0;
            left: 0;

            width: 0;
            height: 3px;

            background:
                linear-gradient(
                    90deg,
                    #111318,
                    var(--accent)
                );

            z-index: 10000;
            opacity: 0;

            transition:
                width .28s var(--ease),
                opacity .18s ease;
        }

        .page-progress.active {
            width: 68%;
            opacity: 1;
        }

        .page-progress.complete {
            width: 100%;
            opacity: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Sidebar
        |--------------------------------------------------------------------------
        */

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;

            width: var(--sidebar-width);
            min-height: 100vh;

            display: flex;
            flex-direction: column;

            background:
                linear-gradient(
                    180deg,
                    #0a0b0f 0%,
                    #111318 100%
                );

            border-right:
                1px solid rgba(255, 255, 255, .08);

            z-index: 1040;

            transition:
                transform .28s var(--ease);
        }

        .sidebar::after {
            content: "";

            position: absolute;
            top: 0;
            right: -1px;

            width: 1px;
            height: 150px;

            background:
                linear-gradient(
                    180deg,
                    var(--accent),
                    transparent
                );

            pointer-events: none;
        }

        .sidebar-brand {
            position: relative;

            padding:
                28px
                22px
                24px;

            border-bottom:
                1px solid rgba(255, 255, 255, .09);
        }

        .sidebar-brand::before {
            content: "BSJ";

            display: inline-grid;
            place-items: center;

            width: 42px;
            height: 42px;

            margin-bottom: 18px;

            border:
                1px solid rgba(255, 255, 255, .16);

            border-radius: 13px;

            color: #ffffff;
            background: var(--accent);

            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;

            box-shadow:
                0 10px 28px rgba(37, 99, 235, .28);
        }

        .brand-kicker {
            margin-bottom: 8px;

            color: #78a3ff;

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .18em;
        }

        .brand-title {
            max-width: 190px;

            color: #ffffff;

            font-size: 19px;
            font-weight: 700;

            line-height: 1.05;
            letter-spacing: -.035em;
        }

        .brand-subtitle {
            max-width: 190px;

            margin-top: 7px;

            color: #898e9b;

            font-size: 10px;
            font-weight: 500;

            line-height: 1.5;
        }

        .sidebar-nav {
            flex: 1;

            overflow-y: auto;

            padding:
                16px
                13px
                28px;

            scrollbar-width: thin;
            scrollbar-color:
                #323640
                transparent;
        }

        .sidebar-section {
            padding:
                21px
                11px
                8px;

            color: #606574;

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .17em;
        }

        .sidebar-link {
            position: relative;

            display: flex;
            align-items: center;

            gap: 11px;

            min-height: 44px;

            margin: 3px 0;

            padding:
                0
                12px;

            border:
                1px solid transparent;

            border-radius: 12px;

            color: #a9aeba;
            text-decoration: none;

            font-size: 12px;
            font-weight: 600;

            transition:
                color .18s ease,
                background-color .18s ease,
                border-color .18s ease,
                transform .18s var(--ease);
        }

        .sidebar-link .nav-icon {
            width: 20px;

            color: #707684;

            text-align: center;
            font-size: 15px;

            transition:
                color .18s ease,
                transform .18s ease;
        }

        .sidebar-link:hover {
            color: #ffffff;

            border-color:
                rgba(255, 255, 255, .08);

            background:
                rgba(255, 255, 255, .05);

            transform:
                translateX(2px);
        }

        .sidebar-link:hover .nav-icon {
            color: #8db0ff;

            transform:
                scale(1.04);
        }

        .sidebar-link.active {
            color: #ffffff;

            border-color:
                rgba(73, 126, 255, .34);

            background:
                linear-gradient(
                    90deg,
                    rgba(37, 99, 235, .25),
                    rgba(37, 99, 235, .08)
                );

            box-shadow:
                inset 0 0 0 1px
                rgba(255, 255, 255, .025);
        }

        .sidebar-link.active .nav-icon {
            color: #86aaff;
        }

        .sidebar-link.active::before {
            content: "";

            position: absolute;
            left: 0;

            width: 3px;
            height: 22px;

            border-radius:
                0
                4px
                4px
                0;

            background: #4f80ff;

            box-shadow:
                0 0 18px
                rgba(79, 128, 255, .55);
        }

        .sidebar-footer {
            padding:
                15px
                14px;

            border-top:
                1px solid rgba(255, 255, 255, .08);
        }

        .account-card {
            display: flex;
            align-items: center;

            gap: 11px;

            padding: 11px;

            border:
                1px solid rgba(255, 255, 255, .08);

            border-radius: 13px;

            background:
                rgba(255, 255, 255, .035);
        }

        .account-avatar {
            width: 38px;
            height: 38px;

            flex: 0 0 38px;

            display: grid;
            place-items: center;

            border-radius: 12px;

            color: #ffffff;
            background: var(--accent);

            font-size: 11px;
            font-weight: 700;

            box-shadow:
                0 8px 20px rgba(37, 99, 235, .20);
        }

        .account-name {
            color: #f4f6fb;

            font-size: 11px;
            font-weight: 600;
        }

        .account-role {
            margin-top: 2px;

            color: #767c89;

            font-size: 9px;
        }

        /*
        |--------------------------------------------------------------------------
        | Main Workspace
        |--------------------------------------------------------------------------
        */

        .workspace {
            min-height: 100vh;

            margin-left:
                var(--sidebar-width);
        }

        /*
        |--------------------------------------------------------------------------
        | Topbar
        |--------------------------------------------------------------------------
        */

        .topbar {
            position: sticky;
            top: 0;

            min-height: 76px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            padding:
                0
                34px;

            background:
                rgba(248, 250, 255, .88);

            backdrop-filter:
                blur(18px);

            border-bottom:
                1px solid rgba(217, 222, 232, .80);

            z-index: 1020;
        }

        .topbar-left {
            display: flex;
            align-items: center;

            gap: 14px;
        }

        .menu-button {
            display: none;

            width: 40px;
            height: 40px;

            place-items: center;

            border:
                1px solid var(--line-strong);

            border-radius: 12px;

            color: var(--ink);
            background: #ffffff;

            font-size: 18px;
        }

        .menu-button:hover {
            color: #ffffff;
            background: var(--ink);
        }

        .page-eyebrow {
            margin-bottom: 3px;

            color: var(--accent);

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .17em;
        }

        .page-name {
            color: var(--ink);

            font-size: 16px;
            font-weight: 700;

            letter-spacing: -.03em;
        }

        .topbar-right {
            display: flex;
            align-items: center;

            gap: 10px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;

            gap: 7px;

            padding:
                8px
                11px;

            border:
                1px solid #b9cdfd;

            border-radius: 999px;

            color: #1746b8;
            background: #edf3ff;

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .logout-button {
            min-height: 38px;

            display: inline-flex;
            align-items: center;

            gap: 7px;

            padding:
                0
                13px;

            border:
                1px solid var(--line-strong);

            border-radius: 11px;

            color: #ffffff;
            background: var(--ink);

            font-size: 10px;
            font-weight: 700;

            transition:
                transform .16s var(--ease),
                background-color .16s ease;
        }

        .logout-button:hover {
            color: #ffffff;
            background: var(--accent);

            transform:
                translateY(-1px);
        }

        /*
        |--------------------------------------------------------------------------
        | Page Content
        |--------------------------------------------------------------------------
        */

        .page-content {
            width: min(
                100%,
                1580px
            );

            margin:
                0
                auto;

            padding:
                34px
                36px
                52px;

            animation:
                contentEnter .34s var(--ease) both;
        }

        .page-content > .d-flex:first-child h3,
        .page-content > div:first-child h3,
        .page-content h1,
        .page-content h2,
        .page-content h3 {
            color: var(--ink);
            letter-spacing: -.04em;
        }

        .page-content h3 {
            font-weight: 700;
        }

        .text-muted {
            color:
                var(--ink-muted) !important;
        }

        @keyframes contentEnter {
            from {
                opacity: 0;

                transform:
                    translateY(7px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Cards
        |--------------------------------------------------------------------------
        */

        .card {
            overflow: hidden;

            border:
                1px solid rgba(16, 18, 24, .14);

            border-radius:
                var(--radius);

            background:
                var(--surface);

            box-shadow:
                var(--shadow-soft);

            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                transform .2s var(--ease);
        }

        .card:hover {
            border-color:
                rgba(16, 18, 24, .28);

            box-shadow:
                var(--shadow);

            transform:
                translateY(-1px);
        }

        .card-header {
            min-height: 58px;

            display: flex;
            align-items: center;

            padding:
                15px
                18px;

            border-bottom:
                1px solid #e7ebf2;

            color: var(--ink);

            background:
                linear-gradient(
                    180deg,
                    #ffffff,
                    #fbfcff
                ) !important;

            font-weight: 700;
        }

        .card-header strong {
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .card-body {
            padding: 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | Feature / KPI Cards
        |--------------------------------------------------------------------------
        */

        .row > [class*="col-"] > .card.h-100 {
            position: relative;
        }

        .row > [class*="col-"] > .card.h-100::before {
            content: "";

            position: absolute;
            inset:
                0
                auto
                0
                0;

            width: 4px;

            background: var(--accent);
        }

        .fs-3.fw-semibold {
            font-size:
                clamp(
                    1.8rem,
                    3vw,
                    2.55rem
                ) !important;

            font-weight:
                700 !important;

            letter-spacing:
                -.055em;
        }

        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .btn {
            min-height: 41px;

            border-radius: 11px;

            font-size: 11px;
            font-weight: 700;

            letter-spacing: -.01em;

            transition:
                transform .15s var(--ease),
                box-shadow .15s ease,
                background-color .15s ease,
                border-color .15s ease,
                color .15s ease;
        }

        .btn:hover {
            transform:
                translateY(-1px);
        }

        .btn:active {
            transform:
                translateY(0)
                scale(.985);
        }

        .btn-primary {
            border-color:
                var(--accent);

            background:
                var(--accent);

            color: #ffffff;

            box-shadow:
                0 8px 20px
                rgba(37, 99, 235, .18);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            border-color:
                #184fd2;

            background:
                #184fd2;

            color: #ffffff;

            box-shadow:
                0 10px 24px
                rgba(37, 99, 235, .24);
        }

        .btn-outline-primary {
            border-color:
                var(--accent);

            color:
                var(--accent-dark);

            background:
                #ffffff;
        }

        .btn-outline-primary:hover {
            border-color:
                var(--accent);

            background:
                var(--accent);

            color: #ffffff;
        }

        .btn-outline-secondary {
            border-color:
                #aeb6c5;

            color:
                #343943;

            background:
                #ffffff;
        }

        .btn-outline-secondary:hover {
            border-color:
                var(--ink);

            color:
                #ffffff;

            background:
                var(--ink);
        }

        .btn-success {
            border-color:
                var(--success);

            background:
                var(--success);
        }

        .btn-danger,
        .btn-outline-danger:hover {
            border-color:
                var(--danger);

            background:
                var(--danger);
        }

        /*
        |--------------------------------------------------------------------------
        | Inputs
        |--------------------------------------------------------------------------
        */

        .form-label {
            margin-bottom: 7px;

            color: #373c47;

            font-size: 10px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .055em;
        }

        .form-control,
        .form-select {
            min-height: 44px;

            border:
                1px solid #cdd4df;

            border-radius: 11px;

            color: var(--ink);
            background: #ffffff;

            font-size: 12px;

            transition:
                border-color .15s ease,
                box-shadow .15s ease,
                background-color .15s ease;
        }

        textarea.form-control {
            min-height: auto;
        }

        .form-control:hover,
        .form-select:hover {
            border-color:
                #aeb7c5;
        }

        .form-control:focus,
        .form-select:focus {
            border-color:
                var(--accent);

            background: #ffffff;

            box-shadow:
                0 0 0 4px
                rgba(37, 99, 235, .10);
        }

        .form-control::placeholder {
            color: #9ba3b1;
        }

        .form-text {
            color: #858d9b;

            font-size: 9px;
        }

        /*
        |--------------------------------------------------------------------------
        | Tables
        |--------------------------------------------------------------------------
        */

        .table {
            --bs-table-bg:
                transparent;

            margin-bottom: 0;
        }

        .table thead th {
            padding:
                13px
                15px;

            border-bottom:
                1px solid #dfe4ec;

            color: #697180;

            background:
                #f5f7fb;

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .08em;

            white-space: nowrap;
        }

        .table tbody td {
            padding:
                15px;

            border-color:
                #edf0f5;

            color: #2d3139;

            font-size: 11px;

            vertical-align: middle;
        }

        .table-hover tbody tr {
            transition:
                background-color .15s ease;
        }

        .table-hover tbody tr:hover {
            background:
                #f6f9ff;
        }

        .table a {
            color: var(--accent-dark);
        }

        /*
        |--------------------------------------------------------------------------
        | Badges
        |--------------------------------------------------------------------------
        */

        .badge {
            padding:
                .52em
                .76em;

            border-radius: 999px;

            font-size: 9px;
            font-weight: 700;

            letter-spacing: .01em;
        }

        .text-bg-primary {
            background:
                var(--accent) !important;
        }

        .text-bg-success {
            background:
                var(--success) !important;
        }

        .text-bg-warning {
            background:
                #c67b13 !important;

            color:
                #ffffff !important;
        }

        .text-bg-info {
            background:
                #2f6fbc !important;

            color:
                #ffffff !important;
        }

        .text-bg-danger {
            background:
                var(--danger) !important;
        }

        .text-bg-secondary {
            background:
                #667085 !important;
        }

        .text-bg-dark {
            background:
                var(--ink) !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Alerts
        |--------------------------------------------------------------------------
        */

        .alert {
            border-radius: 13px;

            font-size: 11px;

            animation:
                alertEnter .25s var(--ease) both;
        }

        .alert-light {
            border-color:
                #dfe4ec !important;

            background:
                #f8faff;
        }

        @keyframes alertEnter {
            from {
                opacity: 0;

                transform:
                    translateY(-4px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination / Details
        |--------------------------------------------------------------------------
        */

        details > summary {
            list-style: none;
        }

        details > summary::-webkit-details-marker {
            display: none;
        }

        .pagination {
            gap: 4px;
        }

        .page-link {
            border:
                1px solid var(--line);

            border-radius:
                9px !important;

            color: #343943;
            background: #ffffff;

            font-size: 10px;
        }

        .active > .page-link {
            border-color:
                var(--accent);

            background:
                var(--accent);
        }

        /*
        |--------------------------------------------------------------------------
        | Modal
        |--------------------------------------------------------------------------
        */

        .modal-content {
            border:
                1px solid rgba(16, 18, 24, .18);

            border-radius: 18px;

            box-shadow:
                0 30px 90px
                rgba(9, 11, 16, .18);
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile Sidebar Backdrop
        |--------------------------------------------------------------------------
        */

        .sidebar-backdrop {
            position: fixed;
            inset: 0;

            z-index: 1035;

            display: none;

            opacity: 0;

            pointer-events: none;

            background:
                rgba(5, 7, 12, .56);

            backdrop-filter:
                blur(3px);

            transition:
                opacity .22s ease;
        }

        .sidebar-backdrop.show {
            opacity: 1;

            pointer-events: auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 991.98px
        ) {
            .sidebar {
                transform:
                    translateX(-100%);
            }

            .sidebar.mobile-open {
                transform:
                    translateX(0);

                box-shadow:
                    22px 0 50px
                    rgba(0, 0, 0, .20);
            }

            .workspace {
                margin-left: 0;
            }

            .sidebar-backdrop {
                display: block;
            }

            .menu-button {
                display: grid;
            }

            .topbar {
                padding:
                    0
                    20px;
            }

            .page-content {
                padding:
                    28px
                    20px
                    44px;
            }
        }

        @media (
            max-width: 575.98px
        ) {
            .topbar {
                min-height: 70px;

                padding:
                    0
                    14px;
            }

            .role-badge {
                display: none;
            }

            .logout-label {
                display: none;
            }

            .logout-button {
                width: 40px;
                padding: 0;
                justify-content: center;
            }

            .page-content {
                padding:
                    22px
                    12px
                    34px;
            }

            .card-body {
                padding: 16px;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Reduced Motion
        |--------------------------------------------------------------------------
        */

        @media (
            prefers-reduced-motion: reduce
        ) {
            *,
            *::before,
            *::after {
                scroll-behavior:
                    auto !important;

                animation-duration:
                    .001ms !important;

                animation-iteration-count:
                    1 !important;

                transition-duration:
                    .001ms !important;
            }
        }
    </style>

    @stack('styles')
</head>

<body>

@php
    $currentUser =
        auth()->user();

    $roleSlug =
        $currentUser?->role?->slug;

    $roleName =
        $currentUser?->role?->name
        ?? 'No Role';

    $canManageResidents =
        in_array(
            $roleSlug,
            [
                'barangay_captain',
                'secretary',
                'staff',
            ],
            true
        );

    $canViewBlotter =
        in_array(
            $roleSlug,
            [
                'barangay_captain',
                'secretary',
                'staff',
                'councilor',
                'lupon',
            ],
            true
        );

    $canCreateBlotter =
        in_array(
            $roleSlug,
            [
                'barangay_captain',
                'secretary',
                'staff',
            ],
            true
        );

    $canViewAnalytics =
        in_array(
            $roleSlug,
            [
                'barangay_captain',
                'secretary',
            ],
            true
        );

    $canManageUsers =
        $roleSlug ===
        'barangay_captain';

    $canAccessMediation =
        in_array(
            $roleSlug,
            [
                'barangay_captain',
                'secretary',
                'lupon',
            ],
            true
        );

    $initials =
        collect(
            preg_split(
                '/\s+/',
                trim(
                    $currentUser?->name
                    ?? 'User'
                )
            )
        )
            ->filter()
            ->take(2)
            ->map(
                fn ($part) =>
                    strtoupper(
                        substr(
                            $part,
                            0,
                            1
                        )
                    )
            )
            ->implode('');
@endphp


<div
    id="pageProgress"
    class="page-progress"
></div>


<div
    id="sidebarBackdrop"
    class="sidebar-backdrop"
></div>


<aside
    id="sidebar"
    class="sidebar"
>

    <div class="sidebar-brand">

        <div class="brand-kicker">
            Barangay Records • Official
        </div>

        <div class="brand-title">
            Barangay San Jose
        </div>

        <div class="brand-subtitle">
            Blotter Management System
        </div>

    </div>


    <nav class="sidebar-nav">

        <div class="sidebar-section">
            Overview
        </div>

        <a
            href="{{ route('dashboard') }}"
            class="sidebar-link
            {{
                request()->routeIs(
                    'dashboard'
                )
                    ? 'active'
                    : ''
            }}"
        >

            <span class="nav-icon">
                <i class="bi bi-grid"></i>
            </span>

            Dashboard

        </a>


        @if($canViewAnalytics)

            <a
                href="{{ route('analytics.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'analytics.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-bar-chart-line"></i>
                </span>

                Business Intelligence

            </a>

        @endif


        @if($canManageResidents)

            <div class="sidebar-section">
                Records
            </div>

            <a
                href="{{ route('residents.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'residents.index',
                        'residents.show',
                        'residents.edit'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-person-vcard"></i>
                </span>

                Complainant Records

            </a>

        @endif


        @if($canViewBlotter)

            <div class="sidebar-section">
                Case Management
            </div>

            <a
                href="{{ route('blotter.index') }}"
                class="sidebar-link
                {{
                    (
                        request()->routeIs(
                            'blotter.index',
                            'blotter.show',
                            'blotter.edit'
                        )
                        &&
                        !request()->filled(
                            'status'
                        )
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-folder2"></i>
                </span>

                @if($roleSlug === 'councilor')

                    Assigned Cases

                @elseif($roleSlug === 'lupon')

                    Assigned Cases

                @else

                    Blotter Cases

                @endif

            </a>


            @if($canCreateBlotter)

                <a
                    href="{{ route('blotter.create') }}"
                    class="sidebar-link
                    {{
                        request()->routeIs(
                            'blotter.create'
                        )
                            ? 'active'
                            : ''
                    }}"
                >

                    <span class="nav-icon">
                        <i class="bi bi-file-earmark-plus"></i>
                    </span>

                    New Case

                </a>

            @endif

        @endif


        @if($canAccessMediation)

            <a
                href="{{
                    route(
                        'blotter.index',
                        [
                            'status'
                                =>
                            'For Mediation'
                        ]
                    )
                }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'blotter.index'
                    )
                    &&
                    request('status')
                        ===
                    'For Mediation'
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-chat-square-text"></i>
                </span>

                @if($roleSlug === 'lupon')

                    My Mediation Cases

                @else

                    Mediation

                @endif

            </a>

        @endif


        @if($canManageUsers)

            <div class="sidebar-section">
                Administration
            </div>

            <a
                href="{{ route('admin.users.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'admin.users.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-person-gear"></i>
                </span>

                User Management

            </a>


            <a
                href="{{ route('admin.audit.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'admin.audit.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-clock-history"></i>
                </span>

                Audit Trail

            </a>

        @endif

    </nav>


    <div class="sidebar-footer">

        <div class="account-card">

            <div class="account-avatar">
                {{ $initials ?: 'U' }}
            </div>

            <div class="overflow-hidden">

                <div
                    class="account-name text-truncate"
                >
                    {{
                        $currentUser?->name
                        ?? 'User'
                    }}
                </div>

                <div
                    class="account-role text-truncate"
                >
                    {{ $roleName }}
                </div>

            </div>

        </div>

    </div>

</aside>


<div class="workspace">

    <header class="topbar">

        <div class="topbar-left">

            <button
                id="menuButton"
                type="button"
                class="menu-button"
                aria-label="Open navigation"
            >
                <i class="bi bi-list"></i>
            </button>


            <div>

                <div class="page-eyebrow">
                    Barangay San Jose
                </div>

                <div class="page-name">
                    @yield(
                        'page-title',
                        'Records Management'
                    )
                </div>

            </div>

        </div>


        <div class="topbar-right">

            <div class="role-badge">

                <i class="bi bi-shield-check"></i>

                {{ $roleName }}

            </div>


            <form
                action="{{ route('logout') }}"
                method="POST"
                class="mb-0"
            >

                @csrf

                <button
                    type="submit"
                    class="logout-button"
                >

                    <i class="bi bi-box-arrow-right"></i>

                    <span class="logout-label">
                        Sign out
                    </span>

                </button>

            </form>

        </div>

    </header>


    <main class="page-content">

        @yield('content')

    </main>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById(
                'sidebar'
            );

        const backdrop =
            document.getElementById(
                'sidebarBackdrop'
            );

        const menuButton =
            document.getElementById(
                'menuButton'
            );

        const progress =
            document.getElementById(
                'pageProgress'
            );


        function openSidebar() {

            sidebar?.classList.add(
                'mobile-open'
            );

            backdrop?.classList.add(
                'show'
            );

            document.body.style.overflow =
                'hidden';
        }


        function closeSidebar() {

            sidebar?.classList.remove(
                'mobile-open'
            );

            backdrop?.classList.remove(
                'show'
            );

            document.body.style.overflow =
                '';
        }


        function startProgress() {

            if (!progress) {
                return;
            }

            progress.classList.remove(
                'complete'
            );

            progress.classList.add(
                'active'
            );
        }


        function finishProgress() {

            if (!progress) {
                return;
            }

            progress.classList.remove(
                'active'
            );

            progress.classList.add(
                'complete'
            );

            setTimeout(
                function () {
                    progress.classList.remove(
                        'complete'
                    );
                },
                280
            );
        }


        menuButton?.addEventListener(
            'click',
            openSidebar
        );


        backdrop?.addEventListener(
            'click',
            closeSidebar
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'Escape'
                ) {
                    closeSidebar();
                }
            }
        );


        document
            .querySelectorAll(
                'a[href]'
            )
            .forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        function (event) {

                            const href =
                                link.getAttribute(
                                    'href'
                                );

                            if (
                                !href
                                ||
                                href === '#'
                                ||
                                href.startsWith(
                                    '#'
                                )
                                ||
                                href.startsWith(
                                    'javascript:'
                                )
                                ||
                                link.target ===
                                    '_blank'
                                ||
                                event.ctrlKey
                                ||
                                event.metaKey
                                ||
                                event.shiftKey
                                ||
                                event.altKey
                            ) {
                                return;
                            }

                            startProgress();

                            if (
                                window.innerWidth
                                <=
                                991
                            ) {
                                closeSidebar();
                            }
                        }
                    );
                }
            );


        document
            .querySelectorAll(
                'form'
            )
            .forEach(
                function (form) {

                    form.addEventListener(
                        'submit',
                        function () {

                            if (
                                form.dataset
                                    .noLoading
                                ===
                                'true'
                            ) {
                                return;
                            }

                            startProgress();
                        }
                    );
                }
            );


        window.addEventListener(
            'pageshow',
            finishProgress
        );

    }
);
</script>


@stack('scripts')

</body>
</html>
