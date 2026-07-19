<?php

namespace App\Services;

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
        $frontendDomain = $this->normalizeDomainInput($data['frontend_domain'] ?? $data['domain'] ?? null);
        $backendDomain = $this->normalizeDomainInput($data['backend_domain'] ?? null);
        $ownerId = $owner?->id ?? $data['owner_id'] ?? null;
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [
            'brand' => 'SoftKatta Solutions',
            'timezone' => 'Asia/Kolkata',
        ];

        if (isset($data['product_domains']) && is_array($data['product_domains'])) {
            $settings['product_domains'] = $this->normalizeProductDomains($data['product_domains']);
        }

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'domain' => $frontendDomain,
            'backend_domain' => $backendDomain,
            'frontend_domain' => $frontendDomain,
            'extra_domains' => $this->normalizeExtraDomains($data['extra_domains'] ?? []),
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

        if (array_key_exists('frontend_domain', $data)) {
            $data['frontend_domain'] = $this->normalizeDomainInput($data['frontend_domain']);
            $data['domain'] = $data['frontend_domain'];
        } elseif (array_key_exists('domain', $data)) {
            $data['domain'] = $this->normalizeDomainInput($data['domain']);
            $data['frontend_domain'] = $data['domain'];
        }

        if (array_key_exists('backend_domain', $data)) {
            $data['backend_domain'] = $this->normalizeDomainInput($data['backend_domain']);
        }

        if (array_key_exists('extra_domains', $data)) {
            $data['extra_domains'] = $this->normalizeExtraDomains($data['extra_domains']);
        }

        if (isset($data['product_domains']) && is_array($data['product_domains'])) {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $settings['product_domains'] = $this->normalizeProductDomains($data['product_domains']);
            $data['settings'] = $settings;
            unset($data['product_domains']);
        }

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
     * @param  mixed  $value
     * @return list<string>
     */
    protected function normalizeExtraDomains(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($domain) => $this->normalizeDomainInput(is_string($domain) ? $domain : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $productDomains
     * @return array<string, array{frontend_domain: ?string, backend_domain: ?string}>
     */
    protected function normalizeProductDomains(array $productDomains): array
    {
        $normalized = [];

        foreach ($productDomains as $slug => $pair) {
            if (! is_string($slug) || $slug === '' || ! is_array($pair)) {
                continue;
            }

            $frontend = $this->normalizeDomainInput($pair['frontend_domain'] ?? null);
            $backend = $this->normalizeDomainInput($pair['backend_domain'] ?? null);

            if ($frontend === null && $backend === null) {
                continue;
            }

            $normalized[$slug] = [
                'frontend_domain' => $frontend,
                'backend_domain' => $backend,
            ];
        }

        return $normalized;
    }
}
