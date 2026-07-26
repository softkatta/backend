<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceCorsOnErrors
{
    /**
     * Ensure CORS headers are present even when an exception occurs.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (\Throwable $e) {
            // Echo the Origin header back when present to satisfy credentialed CORS
            $origin = $request->headers->get('Origin') ?? '*';

            // Minimal error response - avoid leaking internals
            $response = response()->json(['message' => 'Server error'], 500);
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');

            logger()->error($e);

            return $response;
        }
    }
}
