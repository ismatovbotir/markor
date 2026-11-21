<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user the super admin (or company owner) deactivated is logged out on the next request —
 * also on Livewire calls and with a "remember me" cookie — instead of keeping an open session.
 */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($user = Auth::user()) && !$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() || $request->hasHeader('X-Livewire')
                ? response()->json(['message' => __('auth.inactive')], 401)
                : redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
        }

        return $next($request);
    }
}
