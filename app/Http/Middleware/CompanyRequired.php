<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** A registered client must register their company before using the Asl Belgisi area. */
class CompanyRequired
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->isClient() && !$user->company_id) {
            return redirect()->route('company.setup');
        }
        if ($user && $user->isClient() && $user->company && !$user->company->is_active) {
            return response()->view('account.company-blocked', ['company' => $user->company], 403);
        }

        return $next($request);
    }
}
