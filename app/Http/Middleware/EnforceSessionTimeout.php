<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function __construct(
        protected SecurityService $security,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken || ! str_contains($bearerToken, '|')) {
            return $next($request);
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (! $accessToken || ! $this->security->isTokenIdleExpired($accessToken)) {
            return $next($request);
        }

        $accessToken->delete();

        return response()->json([
            'success' => false,
            'message' => 'Your session has expired due to inactivity. Please sign in again.',
            'errors' => ['code' => 'SESSION_EXPIRED'],
        ], 401);
    }
}
