<?php

namespace App\Http\Middleware;

use App\Domain\Stats\VisitCounter;
use Closure;
use Illuminate\Http\Request;

/** Counts successful GET views of public pages by guests (after the response is sent, so pages stay fast). */
class CountVisit
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if ($request->isMethod('GET') && !auth()->check() && $response->getStatusCode() === 200) {
            try {
                VisitCounter::record($request);
            } catch (\Throwable $e) {
                report($e); // a counter must never break a page
            }
        }
    }
}
