<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Login | Barangay San Jose
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f6f9;
        }

        .login-wrapper {
            min-height: 100vh;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
        }
    </style>
</head>

<body>

<div
    class="container login-wrapper d-flex justify-content-center align-items-center"
>

    <div class="card shadow-sm login-card">

        <div class="card-body p-5">

            <div class="text-center mb-4">
                <h3 class="fw-bold">
                    Barangay San Jose
                </h3>

                <p class="text-muted mb-0">
                    Blotter Management System
                </p>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                action="{{ route('login.attempt') }}"
                method="POST"
            >

                @csrf

                <div class="mb-3">

                    <label
                        for="username"
                        class="form-label"
                    >
                        Username
                    </label>

                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        class="form-control"
                        required
                        autofocus
                    >

                </div>

                <div class="mb-3">

                    <label
                        for="password"
                        class="form-label"
                    >
                        Password
                    </label>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="form-control"
                        required
                    >

                </div>

                <div class="form-check mb-3">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="remember"
                        id="remember"
                    >

                    <label
                        class="form-check-label"
                        for="remember"
                    >
                        Remember me
                    </label>

                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >
                    Sign In
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>