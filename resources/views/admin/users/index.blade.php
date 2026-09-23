@extends('layouts.app')

@section('title', 'User Management')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h3 class="mb-1">
            User Management
        </h3>

        <div class="text-muted">
            Manage barangay system accounts and roles.
        </div>

    </div>

    <a
        href="{{ route('admin.users.create') }}"
        class="btn btn-primary"
    >
        Add User
    </a>

</div>


@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


@if($errors->any())

    <div class="alert alert-danger">

        @foreach($errors->all() as $error)

            <div>
                {{ $error }}
            </div>

        @endforeach

    </div>

@endif


<div class="card shadow-sm mb-4">

    <div class="card-body">

        <form
            method="GET"
            action="{{ route('admin.users.index') }}"
        >

            <div class="row g-2">


                <div class="col-md-5">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="Name, username or email"
                    >

                </div>


                <div class="col-md-3">

                    <select
                        name="role_id"
                        class="form-select"
                    >

                        <option value="">
                            All Roles
                        </option>

                        @foreach($roles as $role)

                            <option
                                value="{{ $role->id }}"
                                @selected(
                                    request('role_id')
                                    == $role->id
                                )
                            >
                                {{ $role->name }}
                            </option>

                        @endforeach

                    </select>

                </div>


                <div class="col-md-2">

                    <select
                        name="status"
                        class="form-select"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <option
                            value="active"
                            @selected(
                                request('status')
                                === 'active'
                            )
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            @selected(
                                request('status')
                                === 'inactive'
                            )
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <div class="col-md-2">

                    <button
                        class="btn btn-outline-primary w-100"
                    >
                        Filter
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<div class="card shadow-sm">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead class="table-light">

                <tr>

                    <th>
                        User
                    </th>

                    <th>
                        Username
                    </th>

                    <th>
                        Role
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Last Login
                    </th>

                    <th class="text-end">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($users as $user)

                    <tr>

                        <td>

                            <strong>
                                {{ $user->name }}
                            </strong>

                            <div class="small text-muted">
                                {{ $user->email }}
                            </div>

                        </td>


                        <td>
                            {{ $user->username }}
                        </td>


                        <td>

                            <span class="badge text-bg-secondary">

                                {{
                                    $user
                                        ->role
                                        ?->name
                                    ?? 'No Role'
                                }}

                            </span>

                        </td>


                        <td>

                            @if($user->is_active)

                                <span class="badge text-bg-success">
                                    Active
                                </span>

                            @else

                                <span class="badge text-bg-danger">
                                    Inactive
                                </span>

                            @endif

                        </td>


                        <td>

                            @if($user->last_login_at)

                                {{
                                    $user
                                        ->last_login_at
                                        ->format(
                                            'M d, Y h:i A'
                                        )
                                }}

                            @else

                                <span class="text-muted">
                                    Never
                                </span>

                            @endif

                        </td>


                        <td class="text-end">

                            <div class="d-inline-flex gap-1">


                                <a
                                    href="{{ route(
                                        'admin.users.edit',
                                        $user
                                    ) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    Edit
                                </a>


                                @if(
                                    $user->id !==
                                    auth()->id()
                                )

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'admin.users.toggle-status',
                                            $user
                                        ) }}"
                                        onsubmit="return confirm(
                                            'Change this account status?'
                                        );"
                                    >

                                        @csrf
                                        @method('PATCH')


                                        <button
                                            type="submit"
                                            class="btn btn-sm {{
                                                $user->is_active
                                                    ? 'btn-outline-danger'
                                                    : 'btn-outline-success'
                                            }}"
                                        >

                                            {{
                                                $user->is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'
                                            }}

                                        </button>

                                    </form>

                                @endif

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="text-center text-muted py-4"
                        >
                            No users found.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    @if($users->hasPages())

        <div class="card-footer bg-white">
            {{ $users->links() }}
        </div>

    @endif

</div>

@endsection