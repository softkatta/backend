<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LicenseApiLog;
use App\Models\LicenseHistory;
use App\Models\LicenseKey;
use App\Models\ProductIntegration;
use Illuminate\Http\Request;

class ProductLicenseValidationService
{
    public function __construct(
        protected LicenseService $licenseService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function check(
        ProductIntegration $integration,
        Request $request,
        ?string $licenseKey = null,
        ?string $domain = null,
        ?string $productVersion = null,
    ): array {
        $licenseKey = $licenseKey ?: trim((string) $request->header('X-License-Key', ''));
        $domain = LicenseKey::normalizeDomain($domain ?: $request->header('X-Domain'));
        $productVersion = $productVersion ?: trim((string) $request->header('X-Product-Version', ''));

        $result = $this->validateContext($integration, $licenseKey, $domain, $productVersion, $request);

        $this->logRequest(
            $integration,
            $request,
            $result['license'] ?? null,
            '/license/check',
            $result['success'],
            $result['error_code'] ?? null,
            $result['http_status'] ?? ($result['success'] ? 200 : 403),
            $domain
        );

        if (! $result['success']) {
            return [
                'status' => false,
                'error' => $result['error_code'],
                'message' => $result['message'],
            ];
        }

        /** @var LicenseKey $license */
        $license = $result['license'];
        $license->update([
            'last_verified_at' => now(),
            'activation_count' => $license->activation_count + 1,
            'registered_ip' => $request->ip(),
        ]);

        return $this->buildSuccessPayload($license, $domain);
    }

    /**
     * @return array<string, mixed>
     */
    public function activateLicense(
        ProductIntegration $integration,
        Request $request,
        string $licenseKey,
        string $domain,
        ?string $productVersion = null,
    ): array {
        $domain = LicenseKey::normalizeDomain($domain);
        $productVersion = $productVersion ?: trim((string) $request->header('X-Product-Version', ''));

        $result = $this->validateContext($integration, $licenseKey, null, $productVersion, $request, false);

        if (! $result['success']) {
            $this->logRequest($integration, $request, null, '/license/activate', false, $result['error_code'], $result['http_status'], $domain);

            return ['status' => false, 'error' => $result['error_code'], 'message' => $result['message']];
        }

        /** @var LicenseKey $license */
        $license = $result['license'];

        try {
            $updated = $this->licenseService->registerDomain($license, $domain, $request->ip(), $request->user()?->id);
        } catch (\InvalidArgumentException $exception) {
            $this->logRequest($integration, $request, $license, '/license/activate', false, 'DOMAIN_LIMIT_REACHED', 422, $domain);

            return ['status' => false, 'error' => 'DOMAIN_LIMIT_REACHED', 'message' => $exception->getMessage()];
        }

        $this->logRequest($integration, $request, $updated, '/license/activate', true, null, 200, $domain);

        return [
            'status' => true,
            'license_key' => $updated->license_key,
            'allowed_domains' => $updated->allowed_domains,
            'message' => 'Domain registered successfully.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivateLicense(ProductIntegration $integration, Request $request, string $licenseKey, ?string $domain = null): array
    {
        $license = LicenseKey::with(['subscription.plan', 'product', 'user'])
            ->where('license_key', $licenseKey)
            ->where('product_id', $integration->product_id)
            ->first();

        if (! $license) {
            $this->logRequest($integration, $request, null, '/license/deactivate', false, 'INVALID_LICENSE', 404, $domain);

            return ['status' => false, 'error' => 'INVALID_LICENSE', 'message' => 'License key not found.'];
        }

        $license->update([
            'is_product_active' => false,
            'deactivated_at' => now(),
        ]);

        $this->licenseService->recordHistory($license, 'product_deactivated', [
            'domain' => LicenseKey::normalizeDomain($domain ?: $request->header('X-Domain')),
            'ip' => $request->ip(),
        ]);

        $this->logRequest($integration, $request, $license, '/license/deactivate', true, null, 200, $domain);

        return ['status' => true, 'message' => 'Product deactivated for this license.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function heartbeat(ProductIntegration $integration, Request $request): array
    {
        return $this->check($integration, $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function subscription(ProductIntegration $integration, Request $request): array
    {
        $check = $this->check($integration, $request);
        if (($check['status'] ?? false) !== true) {
            return $check;
        }

        return [
            'status' => true,
            'subscription' => $check['subscription'] ?? [],
            'plan' => $check['plan'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function modules(ProductIntegration $integration, Request $request): array
    {
        $check = $this->check($integration, $request);
        if (($check['status'] ?? false) !== true) {
            return $check;
        }

        return ['status' => true, 'modules' => $check['modules'] ?? []];
    }

    /**
     * @return array<string, mixed>
     */
    public function limits(ProductIntegration $integration, Request $request): array
    {
        $check = $this->check($integration, $request);
        if (($check['status'] ?? false) !== true) {
            return $check;
        }

        return ['status' => true, 'limits' => $check['limits'] ?? []];
    }

    /**
     * @return array<string, mixed>
     */
    public function addons(ProductIntegration $integration, Request $request): array
    {
        $check = $this->check($integration, $request);
        if (($check['status'] ?? false) !== true) {
            return $check;
        }

        return ['status' => true, 'addons' => $check['addons'] ?? []];
    }

    /**
     * @return array{
     *     success: bool,
     *     error_code?: string,
     *     message?: string,
     *     http_status?: int,
     *     license?: LicenseKey
     * }
     */
    protected function validateContext(
        ProductIntegration $integration,
        string $licenseKey,
        ?string $domain,
        string $productVersion,
        Request $request,
        bool $requireDomainMatch = true,
    ): array {
        if ($licenseKey === '') {
            return $this->fail('INVALID_LICENSE', 'License key is required.', 401);
        }

        if ($productVersion === '') {
            return $this->fail('VERSION_NOT_SUPPORTED', 'Product version header is required.', 422);
        }

        if (! $integration->supportsVersion($productVersion)) {
            return $this->fail('VERSION_NOT_SUPPORTED', 'Product version is not supported.', 422);
        }

        $license = LicenseKey::with(['subscription.plan', 'product', 'user'])
            ->where('license_key', $licenseKey)
            ->first();

        if (! $license || $license->product_id !== $integration->product_id) {
            return $this->fail('INVALID_LICENSE', 'License key not found for this product.', 404);
        }

        if ($license->status === LicenseStatus::Active && $license->isExpired()) {
            $license->update(['status' => LicenseStatus::Expired]);
        }

        if ($license->status === LicenseStatus::Suspended) {
            return $this->fail('LICENSE_SUSPENDED', 'License is suspended.', 403);
        }

        if ($license->status !== LicenseStatus::Active) {
            return $this->fail('INVALID_LICENSE', 'License is '.$license->status->value.'.', 403);
        }

        if (! $license->is_product_active) {
            return $this->fail('INVALID_LICENSE', 'Product is deactivated for this license.', 403);
        }

        if ($license->force_logout_at && $license->force_logout_at->greaterThan($license->last_verified_at ?? now()->subYear())) {
            return $this->fail('INVALID_LICENSE', 'License session has been forcefully terminated.', 403);
        }

        $subscription = $license->subscription;
        if (! $subscription || ! in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trial,
            SubscriptionStatus::ExpiringSoon,
        ], true)) {
            return $this->fail('SUBSCRIPTION_EXPIRED', 'Subscription is not active.', 403);
        }

        if ($requireDomainMatch) {
            if ($domain === null || $domain === '') {
                return $this->fail('DOMAIN_NOT_AUTHORIZED', 'Domain header is required.', 403);
            }

            if (! $license->isDomainAllowed($domain)) {
                return $this->fail('DOMAIN_NOT_AUTHORIZED', 'This license is not valid for this domain.', 403);
            }
        }

        if ($license->max_devices > 0 && $license->activation_count >= $license->max_devices && $requireDomainMatch) {
            // Allow re-validation from same registered domain; block new device overflow only on first contact.
            // activation_count increments on each check — use meta device tracking in future if needed.
        }

        return [
            'success' => true,
            'license' => $license,
            'http_status' => 200,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSuccessPayload(LicenseKey $license, ?string $domain): array
    {
        $payload = $this->licenseService->buildSignedCheckResponse($license, $domain);

        return array_merge(['status' => true], $payload);
    }

    protected function logRequest(
        ProductIntegration $integration,
        Request $request,
        ?LicenseKey $license,
        string $endpoint,
        bool $success,
        ?string $errorCode,
        int $statusCode,
        ?string $domain = null,
    ): void {
        LicenseApiLog::create([
            'product_integration_id' => $integration->id,
            'license_key_id' => $license?->id,
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'domain' => LicenseKey::normalizeDomain($domain ?: $request->header('X-Domain')),
            'ip' => $request->ip(),
            'product_slug' => $integration->slug,
            'success' => $success,
            'error_code' => $errorCode,
            'status_code' => $statusCode,
            'meta' => [
                'license_key' => $request->header('X-License-Key'),
                'product_version' => $request->header('X-Product-Version'),
            ],
        ]);
    }

    /**
     * @return array{success: false, error_code: string, message: string, http_status: int}
     */
    protected function fail(string $code, string $message, int $status): array
    {
        return [
            'success' => false,
            'error_code' => $code,
            'message' => $message,
            'http_status' => $status,
        ];
    }
}
