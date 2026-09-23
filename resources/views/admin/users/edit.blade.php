@extends('layouts.app')

@section('title', 'Edit User')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">
            Edit User
        </h3>

        <div class="text-muted">
            {{ $user->name }}
        </div>
    </div>

    <a
        href="{{ route('admin.users.index') }}"
        class="btn btn-outline-secondary"
    >
        Back
    </a>

</div>


@if($errors->any())

    <div class="alert alert-danger">

        <ul class="mb-0">

            @foreach($errors->all() as $error)

                <li>
                    {{ $error }}
                </li>

            @endforeach

        </ul>

    </div>

@endif


<div class="card shadow-sm">

    <div class="card-body">

        <form
            method="POST"
            action="{{ route(
                'admin.users.update',
                $user
            ) }}"
        >

            @csrf
            @method('PUT')

            @include(
                'admin.users._form'
            )

            <hr>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Changes
            </button>

        </form>

    </div>

</div>

@endsection