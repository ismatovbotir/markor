<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isClient = $user->roles()
            ->whereRaw('LOWER(name) = ?', ['client'])
            ->exists();

        if (! $isClient) {
            abort(403);
        }

        return $next($request);
    }
}
