<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** A client page: the super admin is sent to the given admin route instead (`client.only:admin.clients`). */
class ClientOnly
{
    public function handle(Request $request, Closure $next, string $adminRoute)
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route($adminRoute);
        }

        return $next($request);
    }
}
