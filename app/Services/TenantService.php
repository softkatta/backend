<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantService
{
    public function generateSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $owner = null): Tenant
    {
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);
        $ownerId = $owner?->id ?? $data['owner_id'] ?? null;
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [
            'brand' => 'SoftKatta Solutions',
            'timezone' => 'Asia/Kolkata',
        ];

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'domain' => null,
            'backend_domain' => null,
            'frontend_domain' => null,
            'extra_domains' => [],
            'database_name' => $data['database_name'] ?? null,
            'status' => $data['status'] ?? 'active',
            'settings' => $settings,
            'owner_id' => $ownerId,
        ]);

        $this->linkOwner($tenant, $ownerId);

        if (array_key_exists('subscription_domains', $data) && is_array($data['subscription_domains'])) {
            $settings['subscription_domains'] = $this->normalizeSubscriptionDomains(
                $data['subscription_domains'],
                $tenant->fresh(),
                $ownerId ? (int) $ownerId : null,
            );
            $settings = $this->clearPendingForApproved($settings);
            $tenant->update(['settings' => $settings]);
        }

        $this->afterDomainsMaybeReady($tenant->fresh());

        return $tenant->fresh(['owner']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        if (array_key_exists('subscription_domains', $data) && is_array($data['subscription_domains'])) {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $ownerId = isset($data['owner_id']) ? $data['owner_id'] : $tenant->owner_id;
            $settings['subscription_domains'] = $this->normalizeSubscriptionDomains(
                $data['subscription_domains'],
                $tenant,
                $ownerId ? (int) $ownerId : null,
            );
            $settings = $this->clearPendingForApproved($settings);
            $data['settings'] = $settings;
            unset($data['subscription_domains']);
        }

        // Domains live only under settings.subscription_domains — clear legacy columns when saving assignments.
        if (array_key_exists('settings', $data)) {
            $data['frontend_domain'] = null;
            $data['backend_domain'] = null;
            $data['domain'] = null;
            $data['extra_domains'] = [];
        }

        unset($data['product_domains'], $data['extra_domains']);

        $tenant->update($data);

        if (array_key_exists('owner_id', $data)) {
            $this->linkOwner($tenant->fresh(), $data['owner_id']);
        }

        $this->afterDomainsMaybeReady($tenant->fresh());

        return $tenant->fresh(['owner']);
    }

    protected function linkOwner(Tenant $tenant, mixed $ownerId): void
    {
        if (! $ownerId) {
            return;
        }

        User::query()
            ->where('id', $ownerId)
            ->where(function ($query) use ($tenant) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->update(['tenant_id' => $tenant->id]);
    }

    protected function afterDomainsMaybeReady(Tenant $tenant): void
    {
        app(LicenseService::class)->issuePendingLicensesForTenant($tenant);
    }

    protected function normalizeDomainInput(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return array<string, array{subscription_id: int, product_id: int|null, frontend_domain: string, backend_domain: string}>
     */
    protected function normalizeSubscriptionDomains(array $rows, Tenant $tenant, ?int $ownerId): array
    {
        $normalized = [];

        foreach ($rows as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $frontend = $this->normalizeDomainInput($row['frontend_domain'] ?? null);
            $backend = $this->normalizeDomainInput($row['backend_domain'] ?? null);
            if ($frontend === null || $backend === null) {
                continue;
            }

            $subscriptionId = (int) ($row['subscription_id'] ?? 0);
            if ($subscriptionId <= 0) {
                $subscription = $this->createSubscriptionForDomainRow($row, $tenant, $ownerId);
                $subscriptionId = (int) $subscription->id;
            }

            $subscription = Subscription::query()->withoutGlobalScopes()->find($subscriptionId);
            if (! $subscription) {
                throw ValidationException::withMessages([
                    'subscription_domains' => "Subscription #{$subscriptionId} was not found.",
                ]);
            }

            // Keep purchase on this tenant workspace when domains are assigned.
            if (! $subscription->tenant_id) {
                $subscription->update(['tenant_id' => $tenant->id]);
            }

            $productId = isset($row['product_id'])
                ? (int) $row['product_id']
                : (int) $subscription->product_id;

            $normalized[(string) $subscriptionId] = [
                'subscription_id' => $subscriptionId,
                'product_id' => $productId > 0 ? $productId : null,
                'frontend_domain' => $frontend,
                'backend_domain' => $backend,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function createSubscriptionForDomainRow(array $row, Tenant $tenant, ?int $ownerId): Subscription
    {
        if (! $ownerId) {
            throw ValidationException::withMessages([
                'owner_id' => 'Select a customer before adding product domains.',
            ]);
        }

        $productId = (int) ($row['product_id'] ?? 0);
        $planId = (int) ($row['plan_id'] ?? 0);
        if ($productId <= 0 || $planId <= 0) {
            throw ValidationException::withMessages([
                'subscription_domains' => 'Each new domain row needs a product and plan (or an existing subscription).',
            ]);
        }

        $product = Product::query()->find($productId);
        $plan = Plan::query()->find($planId);
        if (! $product || ! $plan) {
            throw ValidationException::withMessages([
                'subscription_domains' => 'Selected product or plan was not found.',
            ]);
        }

        if ((int) $plan->product_id !== (int) $product->id) {
            throw ValidationException::withMessages([
                'subscription_domains' => 'Selected plan does not belong to this product.',
            ]);
        }

        $startsAt = now();
        $endsAt = match ($plan->billing_cycle->value) {
            'yearly' => $startsAt->copy()->addYear(),
            'monthly' => $startsAt->copy()->addMonth(),
            default => $startsAt->copy()->addMonth(),
        };

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'user_id' => $ownerId,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => null,
            'auto_renew' => true,
        ]);

        try {
            app(BillingAdminService::class)->createPaidBillingForSubscription(
                $subscription->fresh(['user', 'product', 'plan']),
                'cash',
            );
        } catch (\Throwable) {
            // Domain assignment should still succeed even if billing records fail.
        }

        return $subscription;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function clearPendingForApproved(array $settings): array
    {
        $approved = is_array($settings['subscription_domains'] ?? null)
            ? $settings['subscription_domains']
            : [];
        $pending = is_array($settings['pending_subscription_domains'] ?? null)
            ? $settings['pending_subscription_domains']
            : [];
        $skips = is_array($settings['subscription_domain_skips'] ?? null)
            ? $settings['subscription_domain_skips']
            : [];

        foreach (array_keys($approved) as $subscriptionId) {
            unset($pending[(string) $subscriptionId], $skips[(string) $subscriptionId]);
        }

        $settings['pending_subscription_domains'] = $pending;
        $settings['subscription_domain_skips'] = $skips;

        return $settings;
    }
}
