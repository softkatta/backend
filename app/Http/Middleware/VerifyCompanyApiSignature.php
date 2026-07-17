<?php

namespace App\Http\Middleware;

use App\Services\CompanyApiAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCompanyApiSignature
{
    public function __construct(
        protected CompanyApiAuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'signed'): Response
    {
        if (app()->environment('local') && $request->boolean('skip_signature')) {
            return $next($request);
        }

        $requireInstallToken = $mode === 'token';

        $result = $this->authService->authenticate($request, $requireInstallToken);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'error_code' => $result['code'],
                'message' => $result['error'],
            ], $result['status']);
        }

        $request->attributes->set('product_integration', $result['integration']);

        return $next($request);
    }
}
