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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    @vite(['resources/css/app.css'])
</head>

<body class="login-page">

<div
    class="container login-wrapper d-flex justify-content-center align-items-center"
>

    <div class="card shadow-sm login-card">

        <div class="login-brand">
            @include('partials.brand')
        </div>

        <div class="card-body login-card-body">

            <div class="login-intro mb-4">
                <h1 class="login-title">Sign in</h1>
                <p class="text-muted mb-0">
                    Access the Blotter Management System.
                </p>
            </div>

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
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
                        autocomplete="username"
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
                        autocomplete="current-password"
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