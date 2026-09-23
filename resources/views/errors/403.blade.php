@extends('layouts.app')

@section('title', 'Access Denied')

@section('content')

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-body text-center p-5">

                    <div
                        class="display-1 fw-bold text-danger"
                    >
                        403
                    </div>

                    <h3 class="mb-3">
                        Access Denied
                    </h3>

                    <p class="text-muted mb-4">

                        Your account does not have permission
                        to access this module or perform this action.

                    </p>

                    <a
                        href="{{ route('dashboard') }}"
                        class="btn btn-primary"
                    >
                        Return to Dashboard
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection