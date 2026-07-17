<?php

namespace App\Services;

use App\Models\LicenseKey;
use App\Models\ProductIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CompanyApiAuthService
{
    public function __construct(
        protected ProductIntegrationService $integrationService,
    ) {}

    /**
     * @return array{integration: ProductIntegration}|array{error: string, code: string, status: int}
     */
    public function authenticate(Request $request, bool $requireInstallToken = false): array
    {
        $productSlug = trim((string) $request->header('X-Product-Slug', ''));
        $timestamp = trim((string) $request->header('X-Timestamp', ''));
        $nonce = trim((string) $request->header('X-Nonce', ''));
        $signature = trim((string) $request->header('X-Signature', ''));
        $domain = trim((string) $request->header('X-Domain', ''));
        $productVersion = trim((string) $request->header('X-Product-Version', ''));
        $installationId = trim((string) $request->header('X-Installation-Id', ''));
        $fingerprint = trim((string) $request->header('X-Server-Fingerprint', ''));

        if ($productSlug === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            return $this->fail('INVALID_SIGNATURE', 'Missing required authentication headers.', 401);
        }

        if (! ctype_digit($timestamp)) {
            return $this->fail('INVALID_SIGNATURE', 'Invalid request timestamp.', 401);
        }

        $skew = (int) config('softkatta.company_timestamp_skew', 300);
        if (abs(time() - (int) $timestamp) > $skew) {
            return $this->fail('EXPIRED_TIMESTAMP', 'Request timestamp is outside the allowed window.', 401);
        }

        $nonceKey = "company_api_nonce:{$productSlug}:{$nonce}";
        if (Cache::has($nonceKey)) {
            return $this->fail('DUPLICATE_NONCE', 'Nonce has already been used.', 401);
        }

        $integration = ProductIntegration::query()
            ->where('slug', $productSlug)
            ->first();

        if (! $integration) {
            return $this->fail('INVALID_API_KEY', 'Product integration not found.', 404);
        }

        if (! $integration->isActive()) {
            return $this->fail('PRODUCT_DISABLED', 'Product integration is inactive.', 403);
        }

        $bearer = $this->extractBearerToken($request);
        if ($bearer === '' || ! hash_equals($integration->public_api_key, $bearer)) {
            return $this->fail('INVALID_API_KEY', 'Invalid API key.', 401);
        }

        $path = '/'.ltrim($request->getPathInfo() ?: $request->path(), '/');
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }
        // Ensure full API path for signature (e.g. /api/v1/company/activate)
        if (! str_starts_with($path, '/api/')) {
            $path = '/api/'.ltrim($path, '/');
        }

        $rawBody = $request->getContent();
        $canonical = $this->canonicalString(
            strtoupper($request->method()),
            $path,
            $timestamp,
            $nonce,
            $productSlug,
            strtolower(LicenseKey::normalizeDomain($domain) ?? $domain),
            $productVersion,
            $installationId,
            $fingerprint,
            $rawBody,
        );

        $expected = hash_hmac('sha256', $canonical, $integration->secret_api_key);

        if (! hash_equals($expected, $signature)) {
            return $this->fail('INVALID_SIGNATURE', 'Invalid request signature.', 401);
        }

        if ($requireInstallToken && trim((string) $request->header('X-Install-Token', '')) === '') {
            return $this->fail('INVALID_INSTALL_TOKEN', 'Install token is required.', 401);
        }

        Cache::put($nonceKey, true, $skew);

        $integration->update(['last_used_at' => now()]);

        return ['integration' => $integration->fresh(['product'])];
    }

    public function canonicalString(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $productSlug,
        string $domain,
        string $productVersion,
        string $installationId,
        string $serverFingerprint,
        string $rawBody = '',
    ): string {
        return implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $productSlug,
            strtolower($domain),
            $productVersion,
            $installationId,
            $serverFingerprint,
            hash('sha256', $rawBody),
        ]);
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
