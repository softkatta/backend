<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSecurityPolicy
{
    public function __construct(
        protected SecurityService $security,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $path = $request->path();

        if ($this->security->ipWhitelistingEnabled()
            && $user->isSuperAdmin()
            && $this->security->isAdminApiPath($path)
            && ! $this->security->ipAllowed($request->ip())) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access is not allowed from this IP address.',
                'errors' => ['code' => 'IP_NOT_ALLOWED'],
            ], 403);
        }

        if ($this->security->mustBlockForTwoFactorSetup($user, $path)) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is required before you can continue.',
                'errors' => ['code' => 'TWO_FACTOR_SETUP_REQUIRED'],
            ], 403);
        }

        return $next($request);
    }
}
