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

    @vite(['resources/css/app.css', 'resources/js/app.js'])



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

    $canViewReports =
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
                        'residents.create',
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

                People Directory

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
                    request()->routeIs(
                        'blotter.index',
                        'blotter.show',
                        'blotter.edit'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-journal-text"></i>
                </span>

                Blotter Records

            </a>


            <a
                href="{{ route('cases.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'cases.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-kanban"></i>
                </span>

                Case Management

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

                    New Blotter Case

                </a>

            @endif

        @endif


        @if($canAccessMediation)

            <a
                href="{{ route('lupon.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'lupon.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-people"></i>
                </span>

                Lupon & Mediation

            </a>


            <a
                href="{{ route('hearings.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'hearings.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-calendar-event"></i>
                </span>

                Hearing Schedules

            </a>


            <a
                href="{{ route('settlements.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'settlements.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-file-earmark-check"></i>
                </span>

                Settlement & Resolutions

            </a>

        @endif


        @if($canViewReports)

            <div class="sidebar-section">
                Reporting
            </div>

            <a
                href="{{ route('reports.index') }}"
                class="sidebar-link
                {{
                    request()->routeIs(
                        'reports.*'
                    )
                        ? 'active'
                        : ''
                }}"
            >

                <span class="nav-icon">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                </span>

                Reports & Export

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

                Audit Logs

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





@stack('scripts')

</body>
</html>
