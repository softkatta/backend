<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LicenseHistory;
use App\Models\LicenseInstallation;
use App\Models\LicenseKey;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
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
            'SOFTKATTA_PUBLIC_API_KEY' => $integration?->public_api_key ?? '',
            'SOFTKATTA_API_SECRET' => $integration?->secret_api_key ?? '',
            'SOFTKATTA_LICENSE_KEY' => $license->license_key,
            'SOFTKATTA_PRODUCT_SLUG' => $product?->installerSlug() ?? '',
            'SOFTKATTA_PRODUCT_VERSION' => $product?->currentVersion() ?? '1.0.0',
            // Legacy aliases (prefer COMPANY_API_URL + PUBLIC_API_KEY + API_SECRET above).
            'SOFTKATTA_API_URL' => config('softkatta.central_api_url'),
            'SOFTKATTA_API_KEY' => $integration?->public_api_key ?? $license->license_key,
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
     *
     * Requires SoftKatta Admin → Tenants frontend + backend domains for the customer workspace.
     *
     * @throws \App\Exceptions\TenantDomainsRequiredException
     */
    public function generateForSubscription(Subscription $subscription): LicenseKey
    {
        // Idempotent — return existing if already generated
        if ($existing = $subscription->licenseKey) {
            return $existing;
        }

        $subscription->loadMissing(['plan', 'product', 'tenant', 'user']);
        $tenant = $this->resolveTenantForSubscription($subscription);
        $product = $subscription->product;

        if (! $tenant || ! $tenant->hasDeployDomains($product, $subscription)) {
            throw new \App\Exceptions\TenantDomainsRequiredException(
                'Assign SoftKatta Admin → Tenants domains for this subscription before generating a license or running project setup.'
            );
        }

        $plan = $subscription->plan;
        $expires = null;

        if ($plan && $plan->billing_cycle->months() !== null) {
            $base = $subscription->starts_at ?? now();
            $expires = $base->copy()->addMonths($plan->billing_cycle->months());
        } elseif ($subscription->ends_at) {
            $expires = $subscription->ends_at;
        }

        $limits = $plan?->limits ?? [];
        $domains = $tenant->deployDomains($product, $subscription);

        return LicenseKey::create([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->product_id,
            'user_id' => $subscription->user_id,
            'license_key' => $this->generateKey($product),
            'allowed_domains' => $domains,
            'max_devices' => $limits['max_devices'] ?? 1,
            'max_domains' => max(
                (int) ($limits['max_domains'] ?? $limits['max_branches'] ?? 1),
                count($domains),
            ),
            'product_version' => $product?->currentVersion(),
            'status' => LicenseStatus::Active,
            'is_product_active' => true,
            'activated_at' => now(),
            'expires_at' => $expires,
            'activation_count' => 0,
        ]);
    }

    /**
     * Sync license allowed_domains from SoftKatta Admin domains for this subscription.
     */
    public function syncAllowedDomainsFromTenant(LicenseKey $license, Tenant $tenant): LicenseKey
    {
        $license->loadMissing(['product', 'subscription']);
        $product = $license->product;
        $subscription = $license->subscription;

        if (! $tenant->hasDeployDomains($product, $subscription)) {
            return $license;
        }

        $domains = $tenant->deployDomains($product, $subscription);
        $license->update([
            'allowed_domains' => $domains,
            'max_domains' => max((int) $license->max_domains, count($domains)),
        ]);

        return $license->fresh();
    }

    public function resolveTenantForSubscription(Subscription $subscription): ?Tenant
    {
        $subscription->loadMissing(['tenant', 'user']);

        if ($subscription->tenant) {
            return $subscription->tenant;
        }

        if ($subscription->tenant_id) {
            return Tenant::query()->find($subscription->tenant_id);
        }

        $user = $subscription->user;
        if ($user?->tenant_id) {
            return Tenant::query()->find($user->tenant_id);
        }

        if ($user) {
            return Tenant::query()->where('owner_id', $user->id)->latest('created_at')->first();
        }

        return null;
    }

    /**
     * Try to issue licenses for active subscriptions once tenant domains are assigned.
     *
     * @return int Number of licenses newly created
     */
    public function issuePendingLicensesForTenant(Tenant $tenant): int
    {
        $created = 0;

        Subscription::query()
            ->withoutGlobalScopes()
            ->with(['plan', 'product', 'licenseKey'])
            ->where(function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
                if ($tenant->owner_id) {
                    $query->orWhere('user_id', $tenant->owner_id);
                }
            })
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial, SubscriptionStatus::ExpiringSoon])
            ->orderBy('id')
            ->each(function (Subscription $subscription) use ($tenant, &$created): void {
                $product = $subscription->product;
                if (! $tenant->hasDeployDomains($product, $subscription)) {
                    return;
                }

                if ($subscription->licenseKey) {
                    $this->syncAllowedDomainsFromTenant($subscription->licenseKey, $tenant);

                    return;
                }

                try {
                    $this->generateForSubscription($subscription);
                    $created++;
                } catch (\App\Exceptions\TenantDomainsRequiredException) {
                    // ignore
                }
            });

        return $created;
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
        $this->revokeRemoteAccess($license, $actorId);

        return $license->fresh();
    }

    public function forceLogout(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update(['force_logout_at' => now()]);
        $this->recordHistory($license, 'force_logout', [], $actorId);
        $this->revokeRemoteAccess($license, $actorId);

        return $license->fresh();
    }

    /**
     * Issue a brand-new license key string for the same subscription/customer.
     * Old key stops working immediately; product must re-activate with the new key.
     */
    public function regenerateKey(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->loadMissing('product');
        $oldKey = $license->license_key;
        $newKey = $this->generateKey($license->product);

        $license->update([
            'license_key' => $newKey,
            'allowed_domains' => [],
            'activation_count' => 0,
            'force_logout_at' => now(),
        ]);

        $this->revokeRemoteAccess($license, $actorId);
        $this->recordHistory($license, 'key_regenerated', [
            'old_key' => $oldKey,
            'new_key' => $newKey,
        ], $actorId);

        return $license->fresh(['product', 'user', 'subscription.plan']);
    }

    public function activateProduct(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update([
            'is_product_active' => true,
            'deactivated_at' => null,
            'force_logout_at' => null,
        ]);
        $this->recordHistory($license, 'product_activated', [], $actorId);

        return $license->fresh();
    }

    public function deactivateProduct(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update([
            'is_product_active' => false,
            'deactivated_at' => now(),
            'force_logout_at' => now(),
        ]);
        $this->recordHistory($license, 'product_deactivated', [], $actorId);
        // Keep install tokens — SoftKatta Activate / product activate restores access automatically via heartbeat.

        return $license->fresh();
    }

    /**
     * Kill install tokens (Force Logout / permanent revoke / domain reset).
     * Do not use this for Suspend — suspend must be reversible without product-side re-activate.
     */
    public function revokeRemoteAccess(LicenseKey $license, ?int $actorId = null): void
    {
        app(CompanyLicenseService::class)->revokeAllInstallations($license, $actorId);
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
                // Products should re-check often so admin suspend/deactivate takes effect quickly.
                'refresh_after' => 300,
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

    public function suspend(LicenseKey $license, string $reason = '', ?int $actorId = null): LicenseKey
    {
        $license->update([
            'status'           => LicenseStatus::Suspended,
            'suspended_at'     => now(),
            'force_logout_at'  => now(),
            'is_product_active'=> false,
            'deactivated_at'   => now(),
        ]);
        $this->recordHistory($license, 'suspended', array_filter(['reason' => $reason]), $actorId);
        // Keep install tokens. Company API verify returns SUSPENDED_LICENSE immediately;
        // Admin Activate restores the same sessions without product-side re-activate.
        // (Force Logout / Revoke / Reset Installations still kill tokens on purpose.)

        return $license->fresh();
    }

    public function revoke(LicenseKey $license, string $reason = '', ?int $actorId = null): LicenseKey
    {
        $license->update([
            'status'           => LicenseStatus::Revoked,
            'revoked_at'       => now(),
            'revoke_reason'    => $reason,
            'force_logout_at'  => now(),
            'is_product_active'=> false,
            'deactivated_at'   => now(),
        ]);
        $this->recordHistory($license, 'revoked', array_filter(['reason' => $reason]), $actorId);
        $this->revokeRemoteAccess($license, $actorId);

        return $license->fresh();
    }

    public function activate(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update([
            'status'            => LicenseStatus::Active,
            'activated_at'      => $license->activated_at ?? now(),
            'suspended_at'      => null,
            'revoked_at'        => null,
            'force_logout_at'   => null,
            'is_product_active' => true,
            'deactivated_at'    => null,
        ]);
        $this->recordHistory($license, 'activated', [], $actorId);

        // If subscription was suspended with the license, restore it so Company API activate succeeds.
        $subscription = $license->subscription;
        if ($subscription && $subscription->status === SubscriptionStatus::Suspend) {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'cancelled_at' => null,
            ]);
        }

        // Revive sessions killed by older Suspend builds that revoked install tokens.
        // Same token hashes stay valid — product recovers on next verify without Restore access.
        LicenseInstallation::query()
            ->where('license_key_id', $license->id)
            ->whereNotNull('revoked_at')
            ->update([
                'revoked_at' => null,
                'last_verified_at' => now(),
            ]);

        return $license->fresh(['subscription']);
    }

    /**
     * After SoftKatta manually finishes product setup on the customer server,
     * notify the customer that their product is ready (email + WhatsApp + in-app).
     *
     * @return array{
     *     customer_name: string,
     *     customer_email: string|null,
     *     customer_phone: string|null,
     *     product_name: string,
     *     product_url: string|null,
     *     channels: list<string>
     * }
     */
    public function notifyProductReady(LicenseKey $license, ?string $productUrl = null, ?int $actorId = null): array
    {
        $license->loadMissing(['user', 'product']);
        $user = $license->user;

        if (! $user) {
            throw new \InvalidArgumentException('This license has no linked customer account.');
        }

        if (! filled($user->email)) {
            throw new \InvalidArgumentException('Customer email is required to send the product-ready notice.');
        }

        $productName = $license->product?->name ?? 'your SoftKatta product';
        $firstName = explode(' ', trim((string) $user->name))[0] ?: 'there';
        $domains = collect($license->allowed_domains ?? [])
            ->filter()
            ->values()
            ->all();

        $url = $this->normalizeProductUrl($productUrl);
        if ($url === null && $domains !== []) {
            $url = $this->normalizeProductUrl((string) $domains[0]);
        }

        $portalUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/').'/login';

        $messageLines = [
            "Hi {$firstName},",
            '',
            "Good news — your {$productName} setup is complete and ready to use.",
            '',
            'License key: '.$license->license_key,
        ];

        if ($url) {
            $messageLines[] = 'Product URL: '.$url;
        }

        if ($domains !== []) {
            $messageLines[] = 'Registered domain(s): '.implode(', ', $domains);
        }

        $messageLines[] = '';
        $messageLines[] = "SoftKatta portal: {$portalUrl}";
        $messageLines[] = '';
        $messageLines[] = 'If you need help signing in or activating the license, reply to this message or contact SoftKatta support.';
        $messageLines[] = '';
        $messageLines[] = '— SoftKatta Team';

        $message = implode("\n", $messageLines);

        $emailDetails = array_filter([
            'Product' => $productName,
            'License key' => $license->license_key,
            'Product URL' => $url,
            'Domain(s)' => $domains !== [] ? implode(', ', $domains) : null,
            'SoftKatta portal' => $portalUrl,
        ], fn ($value) => filled($value));

        app(NotificationService::class)->send(
            $user,
            'product_ready',
            "Your {$productName} is ready",
            $message,
            NotificationService::allChannels(),
            [
                'license_id' => $license->id,
                'product_id' => $license->product_id,
                'product_url' => $url,
            ],
            $emailDetails,
        );

        $this->recordHistory($license, 'product_ready_notified', [
            'product_url' => $url,
            'channels' => ['email', 'whatsapp', 'in_app'],
        ], $actorId);

        return [
            'customer_name' => (string) $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'product_name' => $productName,
            'product_url' => $url,
            'channels' => ['email', 'whatsapp', 'in_app'],
        ];
    }

    private function normalizeProductUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        $url = rtrim($url, '/');

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Mark license expired and kill remote sessions (used by schedule + admin flows).
     */
    public function markExpired(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update([
            'status'           => LicenseStatus::Expired,
            'force_logout_at'  => now(),
            'is_product_active'=> false,
            'deactivated_at'   => now(),
        ]);
        $this->recordHistory($license, 'expired', [], $actorId);
        $this->revokeRemoteAccess($license, $actorId);

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
