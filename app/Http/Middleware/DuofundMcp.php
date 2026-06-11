<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DuofundMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        $token  = env('DUOFUND_MCP_TOKEN');
        $userId = env('DUOFUND_MCP_USER_ID', 1);

        if (!$token) {
            return response()->json(['error' => 'MCP not configured'], 500);
        }

        $provided = $request->bearerToken() ?? $request->header('X-MCP-Token');

        if (!hash_equals((string) $token, (string) $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        auth()->loginUsingId($userId);

        return $next($request);
    }
}
