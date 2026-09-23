<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (! $user->is_active) {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'username' =>
                        'Your account is inactive.',
                ]);
        }

        $roleSlug = $user->role?->slug;

        if (
            ! $roleSlug ||
            ! in_array(
                $roleSlug,
                $roles,
                true
            )
        ) {
            abort(403);
        }

        return $next($request);
    }
}