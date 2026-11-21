<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * One address for the site: markor.uz ⇄ www.markor.uz both resolve, so GET requests to the other variant
 * are sent (301) to the host of APP_URL. Without it the pages exist twice for search engines, and forms
 * opened on one host post to the other (cookies/CSRF don't match → errors).
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = $request->getHost();

        if ($canonical && $host !== $canonical && in_array($request->method(), ['GET', 'HEAD'], true)
            && preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $canonical)) {
            return redirect()->to(rtrim((string) config('app.url'), '/') . $request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
