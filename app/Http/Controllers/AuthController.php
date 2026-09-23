<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Show Login Page
    |--------------------------------------------------------------------------
    */

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        /*
         * Attempt authentication.
         */
        if (
            ! Auth::attempt(
                $credentials,
                $request->boolean('remember')
            )
        ) {
            return back()
                ->withErrors([
                    'username' =>
                        'Invalid username or password.',
                ])
                ->onlyInput('username');
        }

        /*
         * Prevent session fixation.
         */
        $request
            ->session()
            ->regenerate();

        $user = Auth::user();

        /*
         * Reject inactive accounts.
         */
        if (! $user->is_active) {
            Auth::logout();

            $request
                ->session()
                ->invalidate();

            $request
                ->session()
                ->regenerateToken();

            return back()
                ->withErrors([
                    'username' =>
                        'This account is inactive.',
                ])
                ->onlyInput('username');
        }

        /*
         * Update last login timestamp.
         */
        $user->update([
            'last_login_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Audit Trail - Successful Login
        |--------------------------------------------------------------------------
        */

        AuditLogService::log(
            action: 'login',
            module: 'Authentication',
            description:
                "User {$user->username} logged in successfully.",
            auditable: $user,
            newValues: [
                'username' =>
                    $user->username,

                'role' =>
                    $user->role?->slug,

                'last_login_at' =>
                    $user->last_login_at?->toDateTimeString(),
            ],
            userId: $user->id
        );

        return redirect()->intended(
            route('dashboard')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        /*
         * Save user information before logout.
         *
         * Once Auth::logout() runs, auth()->user()
         * and auth()->id() will no longer be available.
         */
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Audit Trail - Logout
        |--------------------------------------------------------------------------
        */

        if ($user) {
            AuditLogService::log(
                action: 'logout',
                module: 'Authentication',
                description:
                    "User {$user->username} logged out.",
                auditable: $user,
                newValues: [
                    'username' =>
                        $user->username,

                    'role' =>
                        $user->role?->slug,
                ],
                userId: $user->id
            );
        }

        /*
         * End authenticated session.
         */
        Auth::logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();

        return redirect()->route('login');
    }
}