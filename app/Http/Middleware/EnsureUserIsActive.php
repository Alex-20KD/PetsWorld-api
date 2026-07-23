<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->is_banned) {
            return response()->json([
                'success' => false,
                'message' => 'La cuenta no está habilitada.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
