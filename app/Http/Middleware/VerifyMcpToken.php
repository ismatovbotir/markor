<?php

namespace App\Http\Middleware;

use App\Support\McpToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** MCP endpoint guard: "Authorization: Bearer <token>" must match the token created in Admin → Settings. */
class VerifyMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!McpToken::check((string) $request->bearerToken())) {
            return response()->json(['jsonrpc' => '2.0', 'error' => ['code' => -32001, 'message' => 'Unauthorized: invalid or missing MCP token.']], 401);
        }

        return $next($request);
    }
}
