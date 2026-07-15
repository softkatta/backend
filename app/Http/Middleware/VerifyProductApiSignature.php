<?php

namespace App\Http\Middleware;

use App\Services\ProductIntegrationAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyProductApiSignature
{
    public function __construct(
        protected ProductIntegrationAuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isLocal() && $request->boolean('skip_signature')) {
            return $next($request);
        }

        $result = $this->authService->authenticate($request);

        if (isset($result['error'])) {
            return response()->json([
                'status' => false,
                'error' => $result['code'],
                'message' => $result['error'],
            ], $result['status']);
        }

        $request->attributes->set('product_integration', $result['integration']);

        return $next($request);
    }
}
