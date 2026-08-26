<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DuofundMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        $token  = config('services.duofund_mcp.token');
        $userId = config('services.duofund_mcp.user_id');

        if (!$token) {
            return response()->json(['error' => 'MCP not configured'], 500);
        }

        $provided = $request->bearerToken() ?? $request->header('X-MCP-Token');

        if (!hash_equals((string) $token, (string) $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        auth()->loginUsingId($userId);

        // Garante que erro de validação volte como JSON 422, e não como redirect.
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
