@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>
        <h3 class="mb-1">
            Audit Trail
        </h3>

        <div class="text-muted">
            Review recorded system activities and user actions.
        </div>
    </div>

</div>


{{-- ========================================================= --}}
{{-- FILTERS --}}
{{-- ========================================================= --}}

<div class="card shadow-sm mb-4">

    <div class="card-header bg-white">
        <strong>
            Filter Audit Logs
        </strong>
    </div>

    <div class="card-body">

        <form
            method="GET"
            action="{{ route('admin.audit.index') }}"
        >

            <div class="row g-3">


                {{-- Search --}}

                <div class="col-md-4">

                    <label class="form-label">
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="User, action, module or description"
                    >

                </div>


                {{-- User --}}

                <div class="col-md-4">

                    <label class="form-label">
                        User
                    </label>

                    <select
                        name="user_id"
                        class="form-select"
                    >

                        <option value="">
                            All Users
                        </option>

                        @foreach($users as $user)

                            <option
                                value="{{ $user->id }}"
                                @selected(
                                    request('user_id') == $user->id
                                )
                            >
                                {{ $user->name }}
                                ({{ $user->username }})
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Action --}}

                <div class="col-md-4">

                    <label class="form-label">
                        Action
                    </label>

                    <select
                        name="action"
                        class="form-select"
                    >

                        <option value="">
                            All Actions
                        </option>

                        @foreach($actions as $action)

                            <option
                                value="{{ $action }}"
                                @selected(
                                    request('action') === $action
                                )
                            >
                                {{ ucfirst($action) }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Module --}}

                <div class="col-md-4">

                    <label class="form-label">
                        Module
                    </label>

                    <select
                        name="module"
                        class="form-select"
                    >

                        <option value="">
                            All Modules
                        </option>

                        @foreach($modules as $module)

                            <option
                                value="{{ $module }}"
                                @selected(
                                    request('module') === $module
                                )
                            >
                                {{ $module }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Date From --}}

                <div class="col-md-4">

                    <label class="form-label">
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        class="form-control"
                        value="{{ request('date_from') }}"
                    >

                </div>


                {{-- Date To --}}

                <div class="col-md-4">

                    <label class="form-label">
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        class="form-control"
                        value="{{ request('date_to') }}"
                    >

                </div>


                {{-- Buttons --}}

                <div class="col-12 d-flex flex-wrap gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="{{ route('admin.audit.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>

                    <a
                        href="{{
                            route(
                                'admin.audit.export',
                                request()->except('page')
                            )
                        }}"
                        class="btn btn-success ms-md-auto"
                    >
                        Export Excel
                    </a>

                </div>

            </div>

        </form>

    </div>

</div>


{{-- ========================================================= --}}
{{-- AUDIT LOG TABLE --}}
{{-- ========================================================= --}}

<div class="card shadow-sm">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <strong>
            Audit Records
        </strong>

        <span class="badge text-bg-secondary">
            {{ number_format($logs->total()) }}
        </span>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead class="table-light">

                <tr>

                    <th>
                        Date / Time
                    </th>

                    <th>
                        User
                    </th>

                    <th>
                        Action
                    </th>

                    <th>
                        Module
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        IP Address
                    </th>

                    <th>
                        Details
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($logs as $log)

                    @php

                        $actionClass = match(
                            strtolower($log->action)
                        ) {
                            'login'
                                => 'text-bg-success',

                            'logout'
                                => 'text-bg-secondary',

                            'created',
                            'create'
                                => 'text-bg-primary',

                            'updated',
                            'update'
                                => 'text-bg-info',

                            'deleted',
                            'delete',
                            'archived',
                            'archive'
                                => 'text-bg-danger',

                            'restored',
                            'restore'
                                => 'text-bg-warning',

                            default
                                => 'text-bg-dark',
                        };

                    @endphp


                    <tr>

                        {{-- Date / Time --}}

                        <td class="text-nowrap">

                            <div>
                                {{
                                    $log
                                        ->created_at
                                        ?->format(
                                            'M d, Y'
                                        )
                                }}
                            </div>

                            <div class="small text-muted">
                                {{
                                    $log
                                        ->created_at
                                        ?->format(
                                            'h:i:s A'
                                        )
                                }}
                            </div>

                        </td>


                        {{-- User --}}

                        <td>

                            @if($log->user)

                                <strong>
                                    {{ $log->user->name }}
                                </strong>

                                <div class="small text-muted">
                                    {{ $log->user->username }}
                                </div>

                                @if($log->user->role)

                                    <div class="small text-muted">
                                        {{ $log->user->role->name }}
                                    </div>

                                @endif

                            @else

                                <span class="text-muted">
                                    System / Deleted User
                                </span>

                            @endif

                        </td>


                        {{-- Action --}}

                        <td>

                            <span class="badge {{ $actionClass }}">
                                {{ ucfirst($log->action) }}
                            </span>

                        </td>


                        {{-- Module --}}

                        <td>

                            {{
                                $log->module
                                ?? '—'
                            }}

                        </td>


                        {{-- Description --}}

                        <td style="min-width: 250px;">

                            {{
                                $log->description
                                ?? '—'
                            }}

                        </td>


                        {{-- IP --}}

                        <td class="text-nowrap">

                            {{
                                $log->ip_address
                                ?? '—'
                            }}

                        </td>


                        {{-- Details --}}

                        <td>

                            @if(
                                !empty($log->old_values)
                                ||
                                !empty($log->new_values)
                                ||
                                $log->auditable_type
                                ||
                                $log->auditable_id
                                ||
                                $log->user_agent
                            )

                                <details>

                                    <summary
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View
                                    </summary>


                                    <div
                                        class="border rounded p-3 mt-2 bg-light"
                                        style="min-width: 320px;"
                                    >


                                        {{-- Related Record --}}

                                        @if(
                                            $log->auditable_type
                                            ||
                                            $log->auditable_id
                                        )

                                            <div class="mb-3">

                                                <div class="text-muted small">
                                                    Related Record
                                                </div>

                                                <div>
                                                    {{
                                                        class_basename(
                                                            $log->auditable_type
                                                            ?? ''
                                                        )
                                                    }}

                                                    @if($log->auditable_id)

                                                        #{{ $log->auditable_id }}

                                                    @endif

                                                </div>

                                            </div>

                                        @endif


                                        {{-- Old Values --}}

                                        @if(!empty($log->old_values))

                                            <div class="mb-3">

                                                <div class="fw-semibold mb-1">
                                                    Previous Values
                                                </div>

                                                <pre
                                                    class="small bg-white border rounded p-2 mb-0"
                                                    style="white-space: pre-wrap;"
                                                >{{ json_encode(
                                                    $log->old_values,
                                                    JSON_PRETTY_PRINT
                                                    | JSON_UNESCAPED_UNICODE
                                                ) }}</pre>

                                            </div>

                                        @endif


                                        {{-- New Values --}}

                                        @if(!empty($log->new_values))

                                            <div class="mb-3">

                                                <div class="fw-semibold mb-1">
                                                    New Values
                                                </div>

                                                <pre
                                                    class="small bg-white border rounded p-2 mb-0"
                                                    style="white-space: pre-wrap;"
                                                >{{ json_encode(
                                                    $log->new_values,
                                                    JSON_PRETTY_PRINT
                                                    | JSON_UNESCAPED_UNICODE
                                                ) }}</pre>

                                            </div>

                                        @endif


                                        {{-- Browser / Device --}}

                                        @if($log->user_agent)

                                            <div>

                                                <div class="text-muted small">
                                                    Browser / Device
                                                </div>

                                                <div class="small text-break">
                                                    {{ $log->user_agent }}
                                                </div>

                                            </div>

                                        @endif

                                    </div>

                                </details>

                            @else

                                <span class="text-muted">
                                    —
                                </span>

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="7"
                            class="text-center text-muted py-5"
                        >
                            No audit records found.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- ========================================================= --}}
    {{-- PAGINATION --}}
    {{-- ========================================================= --}}

    @if($logs->hasPages())

        <div class="card-footer bg-white">

            {{ $logs->links() }}

        </div>

    @endif

</div>

@endsection