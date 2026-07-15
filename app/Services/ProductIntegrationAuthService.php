<?php

namespace App\Services;

use App\Models\LicenseKey;
use App\Models\ProductIntegration;
use Illuminate\Http\Request;

class ProductIntegrationAuthService
{
    public function __construct(
        protected ProductIntegrationService $integrationService,
    ) {}

    /**
     * @return array{integration: ProductIntegration}|array{error: string, code: string, status: int}
     */
    public function authenticate(Request $request): array
    {
        $productSlug = trim((string) $request->header('X-Product-Slug', ''));
        $requestTime = trim((string) $request->header('X-Request-Time', ''));
        $signature = trim((string) $request->header('X-Signature', ''));

        if ($productSlug === '' || $requestTime === '' || $signature === '') {
            return $this->fail('INVALID_SIGNATURE', 'Missing required authentication headers.', 401);
        }

        if (! ctype_digit($requestTime)) {
            return $this->fail('INVALID_SIGNATURE', 'Invalid request timestamp.', 401);
        }

        $age = abs(time() - (int) $requestTime);
        if ($age > 300) {
            return $this->fail('REQUEST_EXPIRED', 'Request timestamp is outside the allowed window.', 401);
        }

        $integration = ProductIntegration::query()
            ->where('slug', $productSlug)
            ->first();

        if (! $integration) {
            return $this->fail('PRODUCT_NOT_FOUND', 'Product integration not found.', 404);
        }

        if (! $integration->isActive()) {
            return $this->fail('PRODUCT_INACTIVE', 'Product integration is inactive.', 403);
        }

        $bearer = $this->extractBearerToken($request);
        if ($bearer === '' || ! hash_equals($integration->public_api_key, $bearer)) {
            return $this->fail('INVALID_API_KEY', 'Invalid API key.', 401);
        }

        $path = '/'.ltrim($request->path(), '/');
        if (! str_starts_with($path, '/api/v1/central')) {
            $path = '/api/v1/central'.(str_starts_with($path, '/central') ? substr($path, 8) : $path);
        }

        $expected = $this->buildSignature(
            (int) $requestTime,
            strtoupper($request->method()),
            $this->normalizeCentralPath($path),
            $request->getContent(),
            $integration->secret_api_key
        );

        if (! hash_equals($expected, $signature)) {
            return $this->fail('INVALID_SIGNATURE', 'Invalid request signature.', 401);
        }

        $integration->update(['last_used_at' => now()]);

        return ['integration' => $integration->fresh(['product'])];
    }

    public function buildSignature(int $timestamp, string $method, string $path, string $body, string $secret): string
    {
        $payload = implode("\n", [
            (string) $timestamp,
            strtoupper($method),
            $this->normalizeCentralPath($path),
            hash('sha256', $body),
        ]);

        return hash_hmac('sha256', $payload, $secret);
    }

    public function normalizeCentralPath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        if (str_starts_with($path, '/api/v1/central')) {
            return $path;
        }

        if (str_starts_with($path, '/central')) {
            return '/api/v1'.$path;
        }

        return '/api/v1/central'.$path;
    }

    /**
     * @return array{error: string, code: string, status: int}
     */
    private function fail(string $code, string $error, int $status): array
    {
        return [
            'error' => $error,
            'code' => $code,
            'status' => $status,
        ];
    }

    private function extractBearerToken(Request $request): string
    {
        $authorization = trim((string) $request->header('Authorization', ''));

        if (str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        return '';
    }
}
