@extends('layouts.app')

@section('title', 'Create User')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">
            Create User
        </h3>

        <div class="text-muted">
            Create a Barangay San Jose system account.
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
            action="{{ route('admin.users.store') }}"
        >

            @csrf

            @include(
                'admin.users._form'
            )

            <hr>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Create User
            </button>

        </form>

    </div>

</div>

@endsection