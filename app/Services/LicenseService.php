<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\TenantDomainsRequiredException;
use App\Models\LicenseHistory;
use App\Models\LicenseInstallation;
use App\Models\LicenseKey;
use App\Models\Plan;
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
        // Ensure meta is present and expose trial end for temporary trial licenses
        $meta = is_array($license->meta) ? $license->meta : [];
        $data['meta'] = $meta;
        $data['trial_ends_at'] = $license->subscription?->trial_ends_at?->toIso8601String();
        $data['installation_env'] = $this->buildInstallationEnv($license);
        $data['installation_env_text'] = $this->formatInstallationEnv($license);

        $planLimits = $license->subscription?->plan?->limits ?? [];
        $payload = $this->buildLimitsPayload($license, is_array($planLimits) ? $planLimits : []);
        $meta = is_array($license->meta) ? $license->meta : [];
        $data['plan_limits'] = [
            'max_users' => (int) ($planLimits['max_users'] ?? $planLimits['max_staff'] ?? 10),
            'max_students' => (int) ($planLimits['max_students'] ?? 500),
        ];
        $data['extra_max_users'] = max(0, (int) ($meta['extra_max_users'] ?? 0));
        $data['extra_max_students'] = max(0, (int) ($meta['extra_max_students'] ?? 0));
        $data['effective_limits'] = [
            'max_users' => $payload['max_users'],
            'max_students' => $payload['max_students'],
            'max_branches' => $payload['max_branches'],
        ];

        $usedUsers = max(0, (int) ($meta['used_users'] ?? 0));
        $usedStudents = max(0, (int) ($meta['used_students'] ?? 0));
        $usageReported = ! empty($meta['usage_reported_at']);
        $data['seat_usage'] = [
            'users' => $usedUsers,
            'students' => $usedStudents,
            'remaining_users' => max(0, (int) $payload['max_users'] - $usedUsers),
            'remaining_students' => max(0, (int) $payload['max_students'] - $usedStudents),
            'reported_at' => $meta['usage_reported_at'] ?? null,
            'is_reported' => $usageReported,
        ];

        $productMeta = is_array($license->product?->meta) ? $license->product->meta : [];
        $data['seat_pricing'] = [
            'price_per_extra_user' => (float) ($productMeta['price_per_extra_user'] ?? 0),
            'price_per_extra_student' => (float) ($productMeta['price_per_extra_student'] ?? 0),
        ];

        return $data;
    }

    /**
     * Generate (or retrieve) a LicenseKey for the given subscription.
     *
     * Requires SoftKatta Admin â†’ Tenants frontend + backend domains for the customer workspace.
     *
     * @throws TenantDomainsRequiredException
     */
    public function generateForSubscription(Subscription $subscription): LicenseKey
    {
        if ($subscription->status === SubscriptionStatus::Trial) {
            return $this->generateTemporaryForSubscription($subscription);
        }

        // Idempotent â€” return existing if already generated
        if ($existing = $subscription->licenseKey) {
            return $existing;
        }

        $subscription->loadMissing(['plan', 'product', 'tenant', 'user']);
        $tenant = $this->resolveTenantForSubscription($subscription);
        $product = $subscription->product;

        if (! $tenant || ! $tenant->hasDeployDomains($product, $subscription)) {
            throw new TenantDomainsRequiredException(
                'Assign SoftKatta Admin â†’ Tenants domains for this subscription before generating a license or running project setup.'
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
     * Generate a temporary license for a trial subscription.
     * This does not require tenant domains and will expire at the subscription's trial_ends_at.
     * Idempotent: returns existing license if present.
     */
    public function generateTemporaryForSubscription(Subscription $subscription): LicenseKey
    {
        if ($existing = $subscription->licenseKey) {
            $meta = is_array($existing->meta) ? $existing->meta : [];
            $existing->update([
                'status' => LicenseStatus::Active,
                'is_product_active' => true,
                'expires_at' => $subscription->trial_ends_at,
                'meta' => array_merge($meta, ['temporary_trial' => true]),
            ]);

            return $existing->fresh();
        }

        $subscription->loadMissing(['plan', 'product', 'tenant', 'user']);
        $product = $subscription->product;

        $expires = $subscription->trial_ends_at ?? null;

        $subscriptionMeta = is_array($subscription->meta) ? $subscription->meta : [];

        $license = LicenseKey::create([
            'subscription_id' => $subscription->id,
            'product_id' => $subscription->product_id,
            'user_id' => $subscription->user_id,
            'license_key' => $this->generateKey($product),
            'allowed_domains' => [],
            'max_devices' => 1,
            'max_domains' => 0,
            'product_version' => $product?->currentVersion(),
            'status' => LicenseStatus::Active,
            'is_product_active' => true,
            'activated_at' => now(),
            'expires_at' => $expires,
            'activation_count' => 0,
            'meta' => array_merge($subscriptionMeta, ['temporary_trial' => true]),
        ]);

        // Notify customer about temporary license (best-effort)
        try {
            $user = $subscription->user;
            if ($user && filled($user->email)) {
                $channels = NotificationService::allChannels();
                app(NotificationService::class)->send(
                    $user,
                    'license.temporary_issued',
                    'Trial license issued',
                    "Your trial license ({$license->license_key}) is active until {$license->expires_at?->toDateString()}",
                    $channels,
                    ['license_id' => $license->id],
                );
            }
        } catch (\Throwable $e) {
            // swallow notification errors
        }

        return $license;
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

        // If this was a temporary trial license and domains are now assigned,
        // promote it to a permanent license by clearing the temporary flag
        // and setting an appropriate expires_at (based on plan / subscription).
        $meta = is_array($license->meta) ? $license->meta : [];
        $isTemporary = ! empty($meta['temporary_trial']);

        if ($isTemporary && count($domains) > 0 && $subscription?->status === SubscriptionStatus::Active) {
            // Compute new expiry similar to generateForSubscription
            $expires = null;
            $plan = $subscription?->plan;

            if ($plan && $plan->billing_cycle->months() !== null) {
                $base = $subscription->starts_at ?? now();
                $expires = $base->copy()->addMonths($plan->billing_cycle->months());
            } elseif ($subscription && $subscription->ends_at) {
                $expires = $subscription->ends_at;
            }

            $meta = $meta;
            unset($meta['temporary_trial']);

            $license->update([
                'meta' => $meta,
                'expires_at' => $expires,
            ]);

            // Notify customer that temporary license has been promoted to permanent
            try {
                $user = $license->user ?? $subscription->user;
                if ($user && filled($user->email)) {
                    $channels = NotificationService::allChannels();
                    app(NotificationService::class)->send(
                        $user,
                        'license.promoted',
                        'License activated',
                        "Your license ({$license->license_key}) is now active for the domains: ".implode(', ', $domains),
                        $channels,
                        ['license_id' => $license->id, 'domains' => $domains],
                    );
                }
            } catch (\Throwable $e) {
                // ignore notification failures
            }
        }

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
                } catch (TenantDomainsRequiredException) {
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
            return $this->errorResponse($license->status->value, 'License is '.$license->status->value.'.');
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

        // Domain transfer requires re-activation â€” revoke all install tokens.
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
        // Keep install tokens â€” SoftKatta Activate / product activate restores access automatically via heartbeat.

        return $license->fresh();
    }

    /**
     * Kill install tokens (Force Logout / permanent revoke / domain reset).
     * Do not use this for Suspend â€” suspend must be reversible without product-side re-activate.
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

        $features = $this->normalizePlanFeatures($plan);

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
            'features' => $features,
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
        $meta = is_array($license->meta) ? $license->meta : [];
        $extraUsers = max(0, (int) ($meta['extra_max_users'] ?? 0));
        $extraStudents = max(0, (int) ($meta['extra_max_students'] ?? 0));

        $planUsers = (int) ($limits['max_users'] ?? $limits['max_staff'] ?? 10);
        $planStudents = (int) ($limits['max_students'] ?? 500);
        $maxUsers = max(0, $planUsers + $extraUsers);
        $maxStudents = max(0, $planStudents + $extraStudents);

        return [
            'max_branches' => (int) ($limits['max_branches'] ?? 1),
            'max_customers' => (int) ($limits['max_customers'] ?? 1000),
            'max_gst_invoices' => (int) ($limits['max_gst_invoices'] ?? 500),
            'max_non_gst_invoices' => (int) ($limits['max_non_gst_invoices'] ?? 500),
            'invoice_limit_period' => (string) ($limits['invoice_limit_period'] ?? 'monthly'),
            // Products enforce max_users; keep max_staff as an alias for older caches.
            'max_users' => $maxUsers,
            'max_staff' => $maxUsers,
            'max_students' => $maxStudents,
            'max_storage_gb' => (int) ($limits['max_storage'] ?? $limits['max_storage_gb'] ?? 5),
            'max_devices' => $license->max_devices,
            'max_domains' => $license->max_domains,
            'plan_max_users' => $planUsers,
            'plan_max_students' => $planStudents,
            'extra_max_users' => $extraUsers,
            'extra_max_students' => $extraStudents,
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
            'status' => LicenseStatus::Suspended,
            'suspended_at' => now(),
            // Do not set force_logout_at â€” that maps to INVALID_INSTALL_TOKEN and breaks
            // Admin Activate auto-recovery. Keep install tokens; verify returns SUSPENDED_LICENSE.
            'is_product_active' => false,
            'deactivated_at' => now(),
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
            'status' => LicenseStatus::Revoked,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
            'force_logout_at' => now(),
            'is_product_active' => false,
            'deactivated_at' => now(),
        ]);
        $this->recordHistory($license, 'revoked', array_filter(['reason' => $reason]), $actorId);
        $this->revokeRemoteAccess($license, $actorId);

        return $license->fresh();
    }

    public function activate(LicenseKey $license, ?int $actorId = null): LicenseKey
    {
        $license->update([
            'status' => LicenseStatus::Active,
            'activated_at' => $license->activated_at ?? now(),
            'suspended_at' => null,
            'revoked_at' => null,
            'force_logout_at' => null,
            'is_product_active' => true,
            'deactivated_at' => null,
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
        // Same token hashes stay valid â€” product recovers on next verify without Restore access.
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
        $license->loadMissing(['user', 'product', 'subscription']);
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

        $portalBaseUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $portalUrl = $portalBaseUrl.'/login';
        $trialEndsAt = $license->subscription?->trial_ends_at ?? $license->expires_at;

        $messageLines = [
            "Hi {$firstName},",
            '',
            "Good news - your {$productName} setup is complete and ready to use.",
            '',
            'Login email: '.$user->email,
            'Password: Use your existing SoftKatta password.',
            'License key: '.$license->license_key,
        ];

        if ($trialEndsAt) {
            $messageLines[] = 'Trial valid until: '.$trialEndsAt->toDateString();
        }

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
        $messageLines[] = 'â€” SoftKatta Team';

        $message = implode("\n", $messageLines);

        $emailDetails = array_filter([
            'Product' => $productName,
            'Login email' => $user->email,
            'License key' => $license->license_key,
            'Trial valid until' => $trialEndsAt?->toDateString(),
            'Product URL' => $url,
            'Domain(s)' => $domains !== [] ? implode(', ', $domains) : null,
            'SoftKatta portal' => $portalUrl,
        ], fn ($value) => filled($value));

        app(NotificationService::class)->send(
            $user,
            'product_ready',
            ($trialEndsAt ? "Your {$productName} trial access details" : "Your {$productName} is ready"),
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
            'status' => LicenseStatus::Expired,
            'force_logout_at' => now(),
            'is_product_active' => false,
            'deactivated_at' => now(),
        ]);
        $this->recordHistory($license, 'expired', [], $actorId);
        $this->revokeRemoteAccess($license, $actorId);

        // Notify customer about expiry (best-effort)
        try {
            $user = $license->user;
            if ($user && filled($user->email)) {
                $channels = NotificationService::allChannels();
                app(NotificationService::class)->send(
                    $user,
                    'license.expired',
                    'License expired',
                    "Your license ({$license->license_key}) has expired.",
                    $channels,
                    ['license_id' => $license->id],
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $license->fresh();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buildSuccessResponse(LicenseKey $license): array
    {
        $plan = $license->subscription?->plan;
        $product = $license->product;
        $user = $license->user;
        $limits = $plan?->limits ?? [];

        // Default modules by product slug if plan has no limits specified
        $modules = $limits['enabled_modules'] ?? $this->defaultModules($product?->slug);

        $features = $this->normalizePlanFeatures($plan);

        return [
            'status' => 'active',
            'license_key' => $license->license_key,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'is_lifetime' => $license->expires_at === null,
            'product' => [
                'id' => $product?->id,
                'name' => $product?->name,
                'slug' => $product?->slug,
                'version' => $product?->meta['current_version'] ?? null,
            ],
            'plan' => [
                'id' => $plan?->id,
                'name' => $plan?->name,
                'billing_cycle' => $plan?->billing_cycle?->value,
                'is_trial' => $license->subscription?->status === SubscriptionStatus::Trial,
            ],
            'limits' => $this->buildLimitsPayload($license, $limits),
            'enabled_modules' => $modules,
            'features' => $features,
            'customer' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'subscription' => [
                'id' => $license->subscription?->id,
                'status' => $license->subscription?->status?->value,
                'starts_at' => $license->subscription?->starts_at?->toIso8601String(),
                'ends_at' => $license->subscription?->ends_at?->toIso8601String(),
            ],
            'allowed_domains' => $license->allowed_domains ?? [],
        ];
    }

    private function errorResponse(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }

    private function normalizePlanFeatures(?Plan $plan): array
    {
        if (! $plan) {
            return [];
        }

        $features = $plan->features;
        if (! is_array($features)) {
            return [];
        }

        $normalized = [];
        foreach ($features as $feature) {
            if (is_string($feature) && trim($feature) !== '') {
                $normalized[] = trim($feature);
            } elseif (is_array($feature)) {
                $value = $feature['name'] ?? $feature['title'] ?? $feature['label'] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $normalized[] = trim($value);
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    public function defaultModules(?string $productSlug): array
    {
        return match ($productSlug) {
            'study-point-erp', 'study-point', 'study-point-management-software', 'coaching-erp' => [
                'students', 'attendance', 'fees', 'batches', 'enquiries', 'reports', 'notices',
            ],
            'kindergarten', 'nursery-school', 'nursery-school-erp', 'nursery-school-management-software' => [
                'admissions', 'attendance', 'fees', 'parent_portal', 'notifications',
            ],
            'medical-store', 'medical-store-management-software' => [
                'billing', 'inventory', 'gst', 'purchase', 'reports',
            ],
            'gold-store', 'gold-store-management-software', 'jewellery-store-management-software' => [
                'billing', 'inventory', 'metal_rates', 'purchases', 'old_gold', 'karigar', 'reports',
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
