<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LicenseHistory;
use App\Models\LicenseKey;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Support\Str;

class LicenseService
{
    private const KEY_PREFIX = 'SK';

    /**
     * Generate a unique license key string.
     */
    private function generateKey(?Product $product = null): string
    {
        $code = 'PROD';
        if ($product) {
            $slugPart = strtoupper(str_replace('-', '', explode('-', $product->installerSlug())[0] ?? 'PROD'));
            $code = substr($slugPart, 0, 8) ?: 'PROD';
        }

        do {
            $key = sprintf(
                '%s-%s-%s-%s',
                self::KEY_PREFIX,
                $code,
                strtoupper(Str::random(5)),
                strtoupper(Str::random(5))
            );
        } while (LicenseKey::where('license_key', $key)->exists());

        return $key;
    }

    /**
     * @return array{
     *     SOFTKATTA_API_URL: string,
     *     SOFTKATTA_API_KEY: string,
     *     SOFTKATTA_PRODUCT_SLUG: string,
     *     SOFTKATTA_PRODUCT_VERSION: string
     * }
     */
    public function buildInstallationEnv(LicenseKey $license): array
    {
        $license->loadMissing(['product', 'product.productIntegration']);
        $product = $license->product;
        $integration = $product?->productIntegration;

        return [
            'SOFTKATTA_COMPANY_API_URL' => config('softkatta.company_api_url'),
            'SOFTKATTA_API_URL' => config('softkatta.central_api_url'),
            'SOFTKATTA_PUBLIC_API_KEY' => $integration?->public_api_key ?? '',
            'SOFTKATTA_API_KEY' => $integration?->public_api_key ?? $license->license_key,
            'SOFTKATTA_LICENSE_KEY' => $license->license_key,
            'SOFTKATTA_PRODUCT_SLUG' => $product?->installerSlug() ?? '',
            'SOFTKATTA_PRODUCT_VERSION' => $product?->currentVersion() ?? '1.0.0',
        ];
    }

    public function formatInstallationEnv(LicenseKey $license): string
    {
        return collect($this->buildInstallationEnv($license))
            ->map(fn (string $value, string $key): string => "{$key}={$value}")
            ->implode("\n");
    }

    /**
     * @return array<string, mixed>
     */
    public function enrichForApi(LicenseKey $license): array
    {
        $license->loadMissing(['product', 'user', 'subscription.plan']);
        $data = $license->toArray();
        $data['installation_env'] = $this->buildInstallationEnv($license);
        $data['installation_env_text'] = $this->formatInstallationEnv($license);

        return $data;
    }

    /**
     * Generate (or retrieve) a LicenseKey for the given subscription.
     */
    public function generateForSubscription(Subscription $subscription): LicenseKey
    {
        // Idempotent — return existing if already generated
        if ($existing = $subscription->licenseKey) {
            return $existing;
        }

        $subscription->loadMissing(['plan', 'product']);
        $plan = $subscription->plan;
        $product = $subscription->product;
        $expires = null;

        if ($plan && $plan->billing_cycle->months() !== null) {
            $base = $subscription->starts_at ?? now();
            $expires = $base->copy()->addMonths($plan->billing_cycle->months());
        } elseif ($subscription->ends_at) {
            $expires = $subscription->ends_at;
        }

        $limits = $plan?->limits ?? [];

        return LicenseKey::create([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->product_id,
            'user_id' => $subscription->user_id,
            'license_key' => $this->generateKey($product),
            'allowed_domains' => [],
            'max_devices' => $limits['max_devices'] ?? 1,
            'max_domains' => $limits['max_domains'] ?? $limits['max_branches'] ?? 1,
            'product_version' => $product?->currentVersion(),
            'status' => LicenseStatus::Active,
            'is_product_active' => true,
            'activated_at' => now(),
            'expires_at' => $expires,
            'activation_count' => 0,
        ]);
    }

    /**
     * Verify a license key for a given domain.
     * Returns an array with status and full context for the product to consume.
     */
    public function verify(string $licenseKey, ?string $domain = null): array
    {
        $license = LicenseKey::with(['subscription.plan', 'product', 'user'])
            ->where('license_key', $licenseKey)
            ->first();

        if (! $license) {
            return $this->errorResponse('invalid', 'License key not found.');
        }

        // Auto-expire
        if ($license->status === LicenseStatus::Active && $license->isExpired()) {
            $license->update(['status' => LicenseStatus::Expired]);
        }

        if ($license->status !== LicenseStatus::Active) {
            return $this->errorResponse($license->status->value, 'License is ' . $license->status->value . '.');
        }

        // Domain check
        if ($domain && ! $license->isDomainAllowed($domain)) {
            return $this->errorResponse('DOMAIN_NOT_AUTHORIZED', 'This license is not valid for this domain.');
        }

        if (! $license->is_product_active) {
            return $this->errorResponse('INVALID_LICENSE', 'Product is deactivated for this license.');
        }

        // Subscription check
        $subscription = $license->subscription;
        if (! $subscription || ! in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trial,
            SubscriptionStatus::ExpiringSoon,
        ])) {
            return $this->errorResponse('subscription_inactive', 'Subscription is not active.');
        }

        // Update last verified timestamp
        $license->update([
            'last_verified_at' => now(),
            'activation_count' => $license->activation_count + 1,
        ]);

        return $this->buildSuccessResponse($license);
    }

    public function registerDomain(LicenseKey $license, string $domain, ?string $ip = null, ?int $actorId = null): LicenseKey
    {
        $domain = LicenseKey::normalizeDomain($domain);

        if ($domain === null || $domain === '') {
            throw new \InvalidArgumentException('A valid domain is required.');
        }

        $domains = collect($license->allowed_domains ?? [])
            ->map(fn ($item) => LicenseKey::normalizeDomain($item))
            ->filter()
            ->values();

        if ($domains->contains($domain)) {
            return $license;
        }

        if ($domains->count() >= $license->max_domains) {
            throw new \InvalidArgumentException('Maximum allowed domains reached for this license.');
        }

        $domains->push($domain);

        $license->update([
            'allowed_domains' => $domains->values()->all(),
            'registered_ip' => $ip,
            'is_product_active' => true,
            'deactivated_at' => null,
        ]);

        $this->recordHistory($license, 'domain_registered', ['domain' => $domain, 'ip' => $ip], $actorId);

        return $license->fresh();
    }

    /**
     * Add a domain to the license's allowed domains.
     */
    public function activateDomain(LicenseKey $license, string $domain): LicenseKey
    {
        return $this->registerDomain($license, $domain);
    }

    /**
     * Remove a domain from allowed domains.
     */
    public function deactivateDomain(LicenseKey $license, string $domain): LicenseKey
    {
        $domains = array_values(array_filter(
            $license->allowed_domains ?? [],
            fn ($d) => $d !== $domain
        ));

        $license->update(['allowed_domains' => $domains]);

        $this->recordHistory($license, 'domain_removed', ['domain' => LicenseKey::normalizeDomain($domain)]);

        return $license->fresh();
    }

    public function resetDomains(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update(['allowed_domains' => []]);
        $this->recordHistory($license, 'domains_reset', [], $actorId);

        // Domain transfer requires re-activation — revoke all install tokens.
        app(CompanyLicenseService::class)->revokeAllInstallations($license, $actorId);

        return $license->fresh();
    }

    public function forceLogout(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update(['force_logout_at' => now()]);
        $this->recordHistory($license, 'force_logout', [], $actorId);
        app(CompanyLicenseService::class)->revokeAllInstallations($license, $actorId);

        return $license->fresh();
    }

    public function activateProduct(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update([
            'is_product_active' => true,
            'deactivated_at' => null,
        ]);
        $this->recordHistory($license, 'product_activated', [], $actorId);

        return $license->fresh();
    }

    public function deactivateProduct(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update([
            'is_product_active' => false,
            'deactivated_at' => now(),
        ]);
        $this->recordHistory($license, 'product_deactivated', [], $actorId);

        return $license->fresh();
    }

    public function recordHistory(LicenseKey $license, string $event, array $meta = [], ?int $actorId = null): void
    {
        LicenseHistory::create([
            'license_key_id' => $license->id,
            'event' => $event,
            'meta' => $meta,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSignedCheckResponse(LicenseKey $license, ?string $domain = null): array
    {
        $plan = $license->subscription?->plan;
        $product = $license->product;
        $user = $license->user;
        $limits = $plan?->limits ?? [];
        $modules = $limits['enabled_modules'] ?? $this->defaultModules($product?->slug);
        $registeredDomain = collect($license->allowed_domains ?? [])->first();

        return [
            'license_status' => $license->status->value,
            'subscription_status' => $license->subscription?->status?->value,
            'customer' => [
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'product' => [
                'slug' => $product?->installerSlug(),
                'version' => $license->product_version ?? $product?->currentVersion(),
            ],
            'domain' => [
                'registered' => $registeredDomain,
                'verified' => $domain ? $license->isDomainAllowed($domain) : false,
                'requested' => $domain,
            ],
            'plan' => [
                'name' => $plan?->name,
                'expires_at' => $license->expires_at?->toIso8601String(),
            ],
            'limits' => $this->buildLimitsPayload($license, $limits),
            'modules' => $modules,
            'addons' => $limits['addons'] ?? [],
            'api' => [
                'refresh_after' => 86400,
            ],
            'subscription' => [
                'id' => $license->subscription?->id,
                'status' => $license->subscription?->status?->value,
                'starts_at' => $license->subscription?->starts_at?->toIso8601String(),
                'ends_at' => $license->subscription?->ends_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $limits
     * @return array<string, mixed>
     */
    private function buildLimitsPayload(LicenseKey $license, array $limits): array
    {
        return [
            'max_branches' => $limits['max_branches'] ?? 1,
            'max_staff' => $limits['max_staff'] ?? 10,
            'max_students' => $limits['max_students'] ?? 500,
            'max_storage_gb' => $limits['max_storage'] ?? 5,
            'max_devices' => $license->max_devices,
            'max_domains' => $license->max_domains,
            'api_access' => $limits['api_access'] ?? false,
            'whatsapp_integration' => $limits['whatsapp_integration'] ?? false,
            'sms_integration' => $limits['sms_integration'] ?? false,
            'email_integration' => $limits['email_integration'] ?? true,
            'biometric_integration' => $limits['biometric_integration'] ?? false,
            'custom_domain' => $limits['custom_domain'] ?? false,
            'white_label' => $limits['white_label'] ?? false,
            'backup' => $limits['backup'] ?? true,
            'addon_support' => $limits['addon_support'] ?? false,
        ];
    }

    public function suspend(LicenseKey $license, string $reason = ''): LicenseKey
    {
        $license->update([
            'status'       => LicenseStatus::Suspended,
            'suspended_at' => now(),
        ]);

        return $license->fresh();
    }

    public function revoke(LicenseKey $license, string $reason = ''): LicenseKey
    {
        $license->update([
            'status'        => LicenseStatus::Revoked,
            'revoked_at'    => now(),
            'revoke_reason' => $reason,
        ]);

        return $license->fresh();
    }

    public function activate(LicenseKey $license): LicenseKey
    {
        $license->update([
            'status'       => LicenseStatus::Active,
            'activated_at' => $license->activated_at ?? now(),
            'suspended_at' => null,
            'revoked_at'   => null,
        ]);

        return $license->fresh();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buildSuccessResponse(LicenseKey $license): array
    {
        $plan    = $license->subscription?->plan;
        $product = $license->product;
        $user    = $license->user;
        $limits  = $plan?->limits ?? [];

        // Default modules by product slug if plan has no limits specified
        $modules = $limits['enabled_modules'] ?? $this->defaultModules($product?->slug);

        return [
            'status'          => 'active',
            'license_key'     => $license->license_key,
            'expires_at'      => $license->expires_at?->toIso8601String(),
            'is_lifetime'     => $license->expires_at === null,
            'product'         => [
                'id'      => $product?->id,
                'name'    => $product?->name,
                'slug'    => $product?->slug,
                'version' => $product?->meta['current_version'] ?? null,
            ],
            'plan'            => [
                'id'            => $plan?->id,
                'name'          => $plan?->name,
                'billing_cycle' => $plan?->billing_cycle?->value,
                'is_trial'      => $license->subscription?->status === SubscriptionStatus::Trial,
            ],
            'limits'          => [
                'max_branches'            => $limits['max_branches'] ?? 1,
                'max_staff'               => $limits['max_staff'] ?? 10,
                'max_students'            => $limits['max_students'] ?? 500,
                'max_storage_gb'          => $limits['max_storage'] ?? 5,
                'max_devices'             => $license->max_devices,
                'api_access'              => $limits['api_access'] ?? false,
                'whatsapp_integration'    => $limits['whatsapp_integration'] ?? false,
                'sms_integration'         => $limits['sms_integration'] ?? false,
                'email_integration'       => $limits['email_integration'] ?? true,
                'biometric_integration'   => $limits['biometric_integration'] ?? false,
                'custom_domain'           => $limits['custom_domain'] ?? false,
                'white_label'             => $limits['white_label'] ?? false,
                'backup'                  => $limits['backup'] ?? true,
                'addon_support'           => $limits['addon_support'] ?? false,
            ],
            'enabled_modules' => $modules,
            'customer'        => [
                'id'    => $user?->id,
                'name'  => $user?->name,
                'email' => $user?->email,
            ],
            'subscription'    => [
                'id'         => $license->subscription?->id,
                'status'     => $license->subscription?->status?->value,
                'starts_at'  => $license->subscription?->starts_at?->toIso8601String(),
                'ends_at'    => $license->subscription?->ends_at?->toIso8601String(),
            ],
            'allowed_domains' => $license->allowed_domains ?? [],
        ];
    }

    private function errorResponse(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }

    private function defaultModules(?string $productSlug): array
    {
        return match ($productSlug) {
            'study-point-erp', 'coaching-erp' => [
                'students', 'attendance', 'fees', 'batches', 'enquiries', 'reports', 'notices',
            ],
            'library-management-system' => [
                'books', 'members', 'issue_return', 'fines', 'reports',
            ],
            'gym-management-system' => [
                'members', 'attendance', 'fees', 'trainers', 'plans', 'reports',
            ],
            default => ['dashboard', 'reports'],
        };
    }
}
