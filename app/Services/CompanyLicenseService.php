<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LicenseApiLog;
use App\Models\LicenseInstallation;
use App\Models\LicenseKey;
use App\Models\ProductIntegration;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyLicenseService
{
    public function __construct(
        protected LicenseService $licenseService,
    ) {}

    /**
     * @return array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}
     */
    public function activate(ProductIntegration $integration, Request $request): array
    {
        $licenseKey = trim((string) $request->input('license_key', ''));
        $domain = LicenseKey::normalizeDomain($request->header('X-Domain'));
        $productVersion = trim((string) $request->header('X-Product-Version', ''));
        $fingerprint = trim((string) $request->header('X-Server-Fingerprint', ''));
        $existingInstallationId = trim((string) ($request->input('installation_id') ?: $request->header('X-Installation-Id', '')));

        $context = $this->validateLicenseContext($integration, $licenseKey, $productVersion, requireDomainMatch: false);

        if (! $context['success']) {
            $this->log($integration, $request, null, '/company/activate', false, $context['error_code'], $context['http_status'], $domain, $fingerprint, $existingInstallationId ?: null);

            return $this->error($context['error_code'], $context['message'], $context['http_status']);
        }

        /** @var LicenseKey $license */
        $license = $context['license'];

        if ($domain === null || $domain === '') {
            $this->log($integration, $request, $license, '/company/activate', false, 'DOMAIN_NOT_AUTHORIZED', 403, $domain, $fingerprint);

            return $this->error('DOMAIN_NOT_AUTHORIZED', 'Domain header is required.', 403);
        }

        if (! $this->isLocalDevDomain($domain) && ! $this->isValidHostname($domain)) {
            $this->log($integration, $request, $license, '/company/activate', false, 'DOMAIN_NOT_AUTHORIZED', 403, $domain, $fingerprint);

            return $this->error('DOMAIN_NOT_AUTHORIZED', 'Invalid domain.', 403);
        }

        if ($fingerprint === '') {
            $this->log($integration, $request, $license, '/company/activate', false, 'SERVER_VERIFICATION_FAILED', 403, $domain, $fingerprint);

            return $this->error('SERVER_VERIFICATION_FAILED', 'Server fingerprint is required.', 403);
        }

        $tenantGate = $this->assertTenantDomainAuthorization($license, $domain);
        if ($tenantGate !== null) {
            $this->log($integration, $request, $license, '/company/activate', false, $tenantGate['error_code'], $tenantGate['http_status'], $domain, $fingerprint);

            return $tenantGate;
        }

        // SoftKatta Admin domains (product-scoped) are the source of truth — sync onto the license.
        $tenant = $this->resolveTenantForLicense($license, $domain);
        if ($tenant) {
            $this->licenseService->syncAllowedDomainsFromTenant($license, $tenant);
            $license->refresh();
        }

        $license->loadMissing('product');
        $registered = collect($license->allowed_domains ?? [])
            ->map(fn ($d) => LicenseKey::normalizeDomain($d))
            ->filter()
            ->values();

        // Prefer product-scoped SoftKatta Admin list for error messaging / match.
        if ($tenant) {
            $adminDomains = collect($tenant->deployDomains($license->product, $license->subscription))
                ->map(fn ($d) => LicenseKey::normalizeDomain($d))
                ->filter()
                ->values();
            if ($adminDomains->isNotEmpty()) {
                $registered = $adminDomains;
            }
        }

        if ($registered->isNotEmpty() && ! $registered->contains($domain)) {
            $this->log($integration, $request, $license, '/company/activate', false, 'DOMAIN_NOT_AUTHORIZED', 403, $domain, $fingerprint);

            return $this->error(
                'DOMAIN_NOT_AUTHORIZED',
                'Detected domain ['.$domain.'] does not match SoftKatta Admin domains ['.$registered->implode(', ').'] for this product. Project setup is only allowed on the assigned domains.',
                403,
            );
        }

        try {
            if ($registered->isEmpty()) {
                $license = $this->licenseService->registerDomain($license, $domain, $request->ip());
            }
        } catch (\InvalidArgumentException $e) {
            $this->log($integration, $request, $license, '/company/activate', false, 'INSTALLATION_LIMIT', 422, $domain, $fingerprint);

            return $this->error('INSTALLATION_LIMIT', $e->getMessage(), 422);
        }

        $activeInstallations = LicenseInstallation::query()
            ->where('license_key_id', $license->id)
            ->whereNull('revoked_at')
            ->get();

        $existing = null;
        if ($existingInstallationId !== '') {
            $existing = $activeInstallations->firstWhere('installation_id', $existingInstallationId);

            // Re-activate after SoftKatta suspend: revive the same revoked installation row.
            if (! $existing) {
                $existing = LicenseInstallation::query()
                    ->where('license_key_id', $license->id)
                    ->where('installation_id', $existingInstallationId)
                    ->first();
            }
        }

        if (! $existing) {
            $existing = $activeInstallations->first(function (LicenseInstallation $row) use ($domain, $fingerprint) {
                return $row->domain === $domain && $row->server_fingerprint === $fingerprint;
            });
        }

        if (! $existing) {
            $existing = LicenseInstallation::query()
                ->where('license_key_id', $license->id)
                ->where('domain', $domain)
                ->where('server_fingerprint', $fingerprint)
                ->whereNotNull('revoked_at')
                ->latest('id')
                ->first();
        }

        if (! $existing && $license->max_devices > 0 && $activeInstallations->count() >= $license->max_devices) {
            // Restore access / re-activate: free slots on this license so the key can bind again.
            foreach ($activeInstallations as $row) {
                $row->update(['revoked_at' => now()]);
            }
            $activeInstallations = collect();
        }

        $installToken = LicenseInstallation::generateToken();
        $refreshToken = LicenseInstallation::generateToken();

        $installation = DB::transaction(function () use ($existing, $license, $domain, $fingerprint, $productVersion, $request, $installToken, $refreshToken) {
            if ($existing) {
                $existing->update([
                    'domain' => $domain,
                    'server_fingerprint' => $fingerprint,
                    'install_token_hash' => LicenseInstallation::hashToken($installToken),
                    'refresh_token_hash' => LicenseInstallation::hashToken($refreshToken),
                    'install_token_expires_at' => now()->addDays(30),
                    'refresh_token_expires_at' => now()->addDays(90),
                    'product_version' => $productVersion,
                    'registered_ip' => $request->ip(),
                    'last_verified_at' => now(),
                    'revoked_at' => null,
                ]);

                return $existing->fresh();
            }

            return LicenseInstallation::create([
                'license_key_id' => $license->id,
                'installation_id' => LicenseInstallation::generateInstallationId(),
                'domain' => $domain,
                'server_fingerprint' => $fingerprint,
                'install_token_hash' => LicenseInstallation::hashToken($installToken),
                'refresh_token_hash' => LicenseInstallation::hashToken($refreshToken),
                'install_token_expires_at' => now()->addDays(30),
                'refresh_token_expires_at' => now()->addDays(90),
                'product_version' => $productVersion,
                'registered_ip' => $request->ip(),
                'last_verified_at' => now(),
            ]);
        });

        $license->update([
            'product_version' => $productVersion,
            'last_verified_at' => now(),
            'registered_ip' => $request->ip(),
            'activation_count' => $license->activation_count + 1,
            // Product-side Restore after Admin Activate — clear any leftover force-logout fence.
            'force_logout_at' => null,
            'is_product_active' => true,
            'deactivated_at' => null,
        ]);

        $this->licenseService->recordHistory($license, 'installation_activated', [
            'installation_id' => $installation->installation_id,
            'domain' => $domain,
            'fingerprint' => $fingerprint,
        ]);

        $this->log($integration, $request, $license, '/company/activate', true, null, 200, $domain, $fingerprint, $installation->installation_id);

        $profile = $this->configurationProfile($license);

        return [
            'success' => true,
            'http_status' => 200,
            'data' => [
                'install_token' => $installToken,
                'refresh_token' => $refreshToken,
                'installation_id' => $installation->installation_id,
                'customer_id' => $license->user_id,
                'product_slug' => $integration->slug,
                'bound_domain' => $domain,
                'configuration_profile' => $profile,
            ],
        ];
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}
     */
    public function verify(ProductIntegration $integration, Request $request): array
    {
        $resolved = $this->resolveInstallation($integration, $request, requireInstallToken: true);

        if (! $resolved['success']) {
            $this->log(
                $integration,
                $request,
                $resolved['license'] ?? null,
                '/company/verify',
                false,
                $resolved['error_code'],
                $resolved['http_status'],
                LicenseKey::normalizeDomain($request->header('X-Domain')),
                trim((string) $request->header('X-Server-Fingerprint', '')),
                trim((string) $request->header('X-Installation-Id', '')) ?: null,
            );

            return $this->error($resolved['error_code'], $resolved['message'], $resolved['http_status']);
        }

        /** @var LicenseKey $license */
        $license = $resolved['license'];
        /** @var LicenseInstallation $installation */
        $installation = $resolved['installation'];

        $installation->update(['last_verified_at' => now()]);
        $license->update(['last_verified_at' => now()]);

        $this->log(
            $integration,
            $request,
            $license,
            '/company/verify',
            true,
            null,
            200,
            $installation->domain,
            $installation->server_fingerprint,
            $installation->installation_id,
        );

        $signed = $this->licenseService->buildSignedCheckResponse($license, $installation->domain);
        $profile = $this->configurationProfile($license);

        return [
            'success' => true,
            'http_status' => 200,
            'data' => [
                'customer_id' => $license->user_id,
                'product_slug' => $integration->slug,
                'bound_domain' => $installation->domain,
                'plan' => $profile['plan'],
                'modules' => $profile['modules'],
                'limits' => $profile['limits'],
                'addons' => $profile['addons'],
                'expires_at' => $profile['expires_at'],
                'refresh_interval' => $profile['refresh_interval'],
                'subscription' => $signed['subscription'] ?? [],
            ],
        ];
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}
     */
    public function refreshToken(ProductIntegration $integration, Request $request): array
    {
        $refreshToken = trim((string) $request->input('refresh_token', ''));
        $installationId = trim((string) ($request->input('installation_id') ?: $request->header('X-Installation-Id', '')));
        $domain = LicenseKey::normalizeDomain($request->header('X-Domain'));
        $fingerprint = trim((string) $request->header('X-Server-Fingerprint', ''));

        if ($refreshToken === '' || $installationId === '') {
            return $this->error('INVALID_INSTALL_TOKEN', 'Refresh token and installation id are required.', 401);
        }

        $installation = LicenseInstallation::query()
            ->where('installation_id', $installationId)
            ->whereNull('revoked_at')
            ->first();

        if (! $installation || ! $installation->matchesRefreshToken($refreshToken)) {
            $this->log($integration, $request, null, '/company/refresh-token', false, 'INVALID_INSTALL_TOKEN', 401, $domain, $fingerprint, $installationId);

            return $this->error('INVALID_INSTALL_TOKEN', 'Invalid refresh token.', 401);
        }

        if ($installation->isRefreshTokenExpired()) {
            return $this->error('INVALID_INSTALL_TOKEN', 'Refresh token has expired.', 401);
        }

        $license = $installation->licenseKey()->with(['subscription.plan', 'product', 'user'])->first();

        if (! $license || $license->product_id !== $integration->product_id) {
            return $this->error('INVALID_LICENSE', 'License not found for this product.', 404);
        }

        $context = $this->validateLicenseContext($integration, $license->license_key, trim((string) $request->header('X-Product-Version', '')), requireDomainMatch: false, license: $license);

        if (! $context['success']) {
            return $this->error($context['error_code'], $context['message'], $context['http_status']);
        }

        if ($domain !== $installation->domain) {
            return $this->error('DOMAIN_NOT_AUTHORIZED', 'Domain does not match this installation.', 403);
        }

        if ($installation->server_fingerprint) {
            if ($fingerprint === '' || ! hash_equals($installation->server_fingerprint, $fingerprint)) {
                return $this->error('SERVER_VERIFICATION_FAILED', 'Server fingerprint mismatch.', 403);
            }
        }

        $installToken = LicenseInstallation::generateToken();
        $newRefresh = LicenseInstallation::generateToken();

        $installation->update([
            'install_token_hash' => LicenseInstallation::hashToken($installToken),
            'refresh_token_hash' => LicenseInstallation::hashToken($newRefresh),
            'install_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(90),
            'last_verified_at' => now(),
            'product_version' => trim((string) $request->header('X-Product-Version', '')) ?: $installation->product_version,
        ]);

        $this->licenseService->recordHistory($license, 'install_token_rotated', [
            'installation_id' => $installation->installation_id,
        ]);

        $this->log($integration, $request, $license, '/company/refresh-token', true, null, 200, $domain, $fingerprint, $installation->installation_id);

        return [
            'success' => true,
            'http_status' => 200,
            'data' => [
                'install_token' => $installToken,
                'refresh_token' => $newRefresh,
                'installation_id' => $installation->installation_id,
            ],
        ];
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}
     */
    public function modules(ProductIntegration $integration, Request $request): array
    {
        $verify = $this->verify($integration, $request);
        if (! ($verify['success'] ?? false)) {
            return $verify;
        }

        return [
            'success' => true,
            'http_status' => 200,
            'data' => [
                'modules' => $verify['data']['modules'] ?? [],
            ],
        ];
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}
     */
    public function limits(ProductIntegration $integration, Request $request): array
    {
        $verify = $this->verify($integration, $request);
        if (! ($verify['success'] ?? false)) {
            return $verify;
        }

        return [
            'success' => true,
            'http_status' => 200,
            'data' => [
                'limits' => $verify['data']['limits'] ?? [],
            ],
        ];
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}
     */
    public function addons(ProductIntegration $integration, Request $request): array
    {
        $verify = $this->verify($integration, $request);
        if (! ($verify['success'] ?? false)) {
            return $verify;
        }

        return [
            'success' => true,
            'http_status' => 200,
            'data' => [
                'addons' => $verify['data']['addons'] ?? [],
            ],
        ];
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, error_code?: string, message?: string, http_status: int}
     */
    public function heartbeat(ProductIntegration $integration, Request $request): array
    {
        return $this->verify($integration, $request);
    }

    public function revokeInstallation(LicenseInstallation $installation, ?int $actorId = null): LicenseInstallation
    {
        $installation->update(['revoked_at' => now()]);
        $license = $installation->licenseKey;
        if ($license) {
            $this->licenseService->recordHistory($license, 'installation_revoked', [
                'installation_id' => $installation->installation_id,
            ], $actorId);
        }

        return $installation->fresh();
    }

    public function revokeAllInstallations(LicenseKey $license, ?int $actorId = null): void
    {
        LicenseInstallation::query()
            ->where('license_key_id', $license->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->licenseService->recordHistory($license, 'installations_revoked', [], $actorId);
    }

    /**
     * @return array{success: bool, license?: LicenseKey, installation?: LicenseInstallation, error_code?: string, message?: string, http_status?: int}
     */
    protected function resolveInstallation(ProductIntegration $integration, Request $request, bool $requireInstallToken = true): array
    {
        $installToken = trim((string) $request->header('X-Install-Token', ''));
        $installationId = trim((string) $request->header('X-Installation-Id', ''));
        $domain = LicenseKey::normalizeDomain($request->header('X-Domain'));
        $fingerprint = trim((string) $request->header('X-Server-Fingerprint', ''));
        $productVersion = trim((string) $request->header('X-Product-Version', ''));

        if ($requireInstallToken && ($installToken === '' || $installationId === '')) {
            return $this->failResolve('INVALID_INSTALL_TOKEN', 'Install token and installation id are required.', 401);
        }

        $installation = LicenseInstallation::query()
            ->where('installation_id', $installationId)
            ->whereNull('revoked_at')
            ->first();

        if (! $installation || ! $installation->matchesInstallToken($installToken)) {
            return $this->failResolve('INVALID_INSTALL_TOKEN', 'Invalid install token.', 401);
        }

        if ($installation->isInstallTokenExpired()) {
            return $this->failResolve('INVALID_INSTALL_TOKEN', 'Install token has expired.', 401);
        }

        $license = $installation->licenseKey()->with(['subscription.plan', 'product', 'user'])->first();

        if (! $license) {
            return $this->failResolve('INVALID_LICENSE', 'License not found.', 404);
        }

        $context = $this->validateLicenseContext($integration, $license->license_key, $productVersion, requireDomainMatch: false, license: $license);

        if (! $context['success']) {
            return $this->failResolve($context['error_code'], $context['message'], $context['http_status'], $license);
        }

        if ($domain === null || $domain === '' || $domain !== $installation->domain) {
            return $this->failResolve('DOMAIN_NOT_AUTHORIZED', 'Domain does not match this installation.', 403, $license, $installation);
        }

        $tenantGate = $this->assertTenantDomainAuthorization($license, $domain);
        if ($tenantGate !== null) {
            return $this->failResolve($tenantGate['error_code'], $tenantGate['message'], $tenantGate['http_status'], $license, $installation);
        }

        if (! $license->isDomainAllowed($domain)) {
            return $this->failResolve('DOMAIN_NOT_AUTHORIZED', 'This license is not valid for this domain.', 403, $license, $installation);
        }

        if ($installation->server_fingerprint) {
            if ($fingerprint === '' || ! hash_equals($installation->server_fingerprint, $fingerprint)) {
                return $this->failResolve('SERVER_VERIFICATION_FAILED', 'Server fingerprint mismatch.', 403, $license, $installation);
            }
        }

        if ($license->force_logout_at && $license->force_logout_at->greaterThan($installation->last_verified_at ?? now()->subYear())) {
            return $this->failResolve('INVALID_INSTALL_TOKEN', 'License session has been forcefully terminated.', 403, $license, $installation);
        }

        return [
            'success' => true,
            'license' => $license,
            'installation' => $installation,
        ];
    }

    /**
     * @return array{success: bool, license?: LicenseKey, error_code?: string, message?: string, http_status?: int}
     */
    protected function validateLicenseContext(
        ProductIntegration $integration,
        string $licenseKey,
        string $productVersion,
        bool $requireDomainMatch = true,
        ?LicenseKey $license = null,
    ): array {
        if ($licenseKey === '') {
            return $this->failResolve('INVALID_LICENSE', 'License key is required.', 401);
        }

        if ($productVersion === '') {
            return $this->failResolve('UNSUPPORTED_VERSION', 'Product version header is required.', 422);
        }

        if (! $integration->supportsVersion($productVersion)) {
            return $this->failResolve('UNSUPPORTED_VERSION', 'Product version is not supported.', 422);
        }

        $license ??= LicenseKey::with(['subscription.plan', 'product', 'user'])
            ->where('license_key', $licenseKey)
            ->first();

        if (! $license || $license->product_id !== $integration->product_id) {
            return $this->failResolve('INVALID_LICENSE', 'License key not found for this product.', 404);
        }

        if ($license->status === LicenseStatus::Active && $license->isExpired()) {
            $license->update(['status' => LicenseStatus::Expired]);
        }

        if ($license->status === LicenseStatus::Suspended) {
            return $this->failResolve('SUSPENDED_LICENSE', 'License is suspended.', 403, $license);
        }

        if ($license->status === LicenseStatus::Revoked) {
            return $this->failResolve('REVOKED_LICENSE', 'License is revoked.', 403, $license);
        }

        if ($license->status === LicenseStatus::Expired) {
            return $this->failResolve('EXPIRED_SUBSCRIPTION', 'License has expired.', 403, $license);
        }

        if ($license->status !== LicenseStatus::Active) {
            return $this->failResolve('INVALID_LICENSE', 'License is '.$license->status->value.'.', 403, $license);
        }

        if (! $license->is_product_active) {
            return $this->failResolve('PRODUCT_DISABLED', 'Product is deactivated for this license.', 403, $license);
        }

        $subscription = $license->subscription;
        if (! $subscription || ! in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trial,
            SubscriptionStatus::ExpiringSoon,
        ], true)) {
            return $this->failResolve('EXPIRED_SUBSCRIPTION', 'Subscription is not active.', 403, $license);
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
    protected function configurationProfile(LicenseKey $license): array
    {
        $signed = $this->licenseService->buildSignedCheckResponse($license);
        $plan = $license->subscription?->plan;

        return [
            'modules' => $this->normalizeModules($signed['modules'] ?? []),
            'limits' => $signed['limits'] ?? [],
            'addons' => $signed['addons'] ?? [],
            'plan' => $plan?->name ?? $plan?->slug ?? null,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'refresh_interval' => $signed['api']['refresh_after'] ?? 300,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $modules
     * @return array<string, bool>
     */
    protected function normalizeModules(array $modules): array
    {
        if ($modules === []) {
            return [];
        }

        // Already a map of flags
        if (! array_is_list($modules)) {
            $out = [];
            foreach ($modules as $key => $value) {
                $out[(string) $key] = (bool) $value;
            }

            return $out;
        }

        // List of enabled module keys
        $out = [];
        foreach ($modules as $key) {
            $out[(string) $key] = true;
        }

        return $out;
    }

    protected function log(
        ProductIntegration $integration,
        Request $request,
        ?LicenseKey $license,
        string $endpoint,
        bool $success,
        ?string $errorCode,
        int $statusCode,
        ?string $domain = null,
        ?string $fingerprint = null,
        ?string $installationId = null,
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
                'product_version' => $request->header('X-Product-Version'),
                'server_fingerprint' => $fingerprint ?: $request->header('X-Server-Fingerprint'),
                'installation_id' => $installationId ?: $request->header('X-Installation-Id'),
                'nonce' => substr((string) $request->header('X-Nonce', ''), 0, 16),
            ],
        ]);
    }

    /**
     * @return array{success: false, error_code: string, message: string, http_status: int}
     */
    protected function error(string $code, string $message, int $status): array
    {
        return [
            'success' => false,
            'error_code' => $code,
            'message' => $message,
            'http_status' => $status,
        ];
    }

    /**
     * @return array{success: false, error_code: string, message: string, http_status: int, license?: LicenseKey, installation?: LicenseInstallation}
     */
    protected function failResolve(string $code, string $message, int $status, ?LicenseKey $license = null, ?LicenseInstallation $installation = null): array
    {
        $payload = [
            'success' => false,
            'error_code' => $code,
            'message' => $message,
            'http_status' => $status,
        ];

        if ($license) {
            $payload['license'] = $license;
        }

        if ($installation) {
            $payload['installation'] = $installation;
        }

        return $payload;
    }

    /**
     * SoftKatta Admin → Tenants domains (per product) must match the install host.
     *
     * @return array{success: false, error_code: string, message: string, http_status: int}|null
     */
    protected function assertTenantDomainAuthorization(LicenseKey $license, string $domain): ?array
    {
        $license->loadMissing(['product', 'subscription']);
        $product = $license->product;
        $subscription = $license->subscription;
        $tenant = $this->resolveTenantForLicense($license, $domain);

        if (! $tenant || ! $tenant->hasDeployDomains($product, $subscription)) {
            $productLabel = $product?->name ?? 'this product';

            return $this->error(
                'TENANT_DOMAINS_REQUIRED',
                "Assign frontend and backend domains for {$productLabel} (this subscription) in SoftKatta Admin → Tenants before project setup.",
                403,
            );
        }

        if (! $tenant->allowsDeployDomain($domain, $product, $subscription)) {
            $assigned = implode(', ', $tenant->deployDomains($product, $subscription));
            $productLabel = $product?->name ?? 'this product';

            return $this->error(
                'DOMAIN_NOT_AUTHORIZED',
                "Detected domain [{$domain}] does not match SoftKatta Admin domains [{$assigned}] for {$productLabel}. Each purchase has its own domains — setup is only allowed on the assigned domains.",
                403,
            );
        }

        return null;
    }

    protected function resolveTenantForLicense(LicenseKey $license, ?string $forDomain = null): ?Tenant
    {
        $license->loadMissing(['subscription.tenant', 'subscription.product', 'user', 'product']);
        $product = $license->product ?? $license->subscription?->product;
        $subscription = $license->subscription;
        $user = $license->user ?? $subscription?->user;

        if ($subscription) {
            $tenant = $this->licenseService->resolveTenantForSubscription($subscription);
            if ($tenant) {
                // Always return the subscription tenant so domain mismatches surface as
                // DOMAIN_NOT_AUTHORIZED (with assigned list), not TENANT_DOMAINS_REQUIRED.
                return $tenant;
            }
        }

        if ($user && $forDomain) {
            $owned = Tenant::query()->where('owner_id', $user->id)->get();
            $matching = $owned->first(fn (Tenant $tenant) => $tenant->allowsDeployDomain($forDomain, $product, $subscription));
            if ($matching) {
                return $matching;
            }

            $withDomains = $owned->first(fn (Tenant $tenant) => $tenant->hasDeployDomains($product, $subscription));
            if ($withDomains) {
                return $withDomains;
            }
        }

        if ($user?->tenant_id) {
            return Tenant::query()->find($user->tenant_id);
        }

        if ($user) {
            return Tenant::query()->where('owner_id', $user->id)->latest('created_at')->first();
        }

        return null;
    }

    protected function isLocalDevDomain(string $domain): bool
    {
        if (! app()->environment('local')) {
            return in_array($domain, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($domain, '.local')
                || str_ends_with($domain, '.test');
        }

        return in_array($domain, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($domain, '.local')
            || str_ends_with($domain, '.test');
    }

    protected function isValidHostname(string $domain): bool
    {
        return (bool) preg_match('/^[a-z0-9.-]+$/i', $domain);
    }
}
