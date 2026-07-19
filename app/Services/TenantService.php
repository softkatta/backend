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

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'domain' => $frontendDomain,
            'backend_domain' => $backendDomain,
            'frontend_domain' => $frontendDomain,
            'database_name' => $data['database_name'] ?? null,
            'status' => $data['status'] ?? 'active',
            'settings' => $data['settings'] ?? [
                'brand' => 'SoftKatta Solutions',
                'timezone' => 'Asia/Kolkata',
            ],
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
        if (! $tenant->hasDeployDomains()) {
            return;
        }

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
}
