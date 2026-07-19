<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

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

        if (array_key_exists('subscription_domains', $data) && is_array($data['subscription_domains'])) {
            $settings['subscription_domains'] = $this->normalizeSubscriptionDomains($data['subscription_domains']);
        }

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
        $this->afterDomainsMaybeReady($tenant);

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
            $settings['subscription_domains'] = $this->normalizeSubscriptionDomains($data['subscription_domains']);
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
    protected function normalizeSubscriptionDomains(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $subscriptionId = (int) ($row['subscription_id'] ?? $key);
            if ($subscriptionId <= 0) {
                continue;
            }

            $frontend = $this->normalizeDomainInput($row['frontend_domain'] ?? null);
            $backend = $this->normalizeDomainInput($row['backend_domain'] ?? null);
            if ($frontend === null || $backend === null) {
                continue;
            }

            $subscription = Subscription::query()->withoutGlobalScopes()->find($subscriptionId);
            $productId = isset($row['product_id'])
                ? (int) $row['product_id']
                : ($subscription?->product_id);

            $normalized[(string) $subscriptionId] = [
                'subscription_id' => $subscriptionId,
                'product_id' => $productId,
                'frontend_domain' => $frontend,
                'backend_domain' => $backend,
            ];
        }

        return $normalized;
    }
}
