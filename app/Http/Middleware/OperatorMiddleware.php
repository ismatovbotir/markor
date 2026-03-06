<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperatorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isOperator = $user->roles()
            ->whereRaw('LOWER(name) = ?', ['operator'])
            ->exists();

        if (! $isOperator) {
            abort(403);
        }

        return $next($request);
    }
}
