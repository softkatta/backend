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

        return Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'domain' => $data['domain'] ?? null,
            'database_name' => $data['database_name'] ?? null,
            'status' => $data['status'] ?? 'active',
            'settings' => $data['settings'] ?? [
                'brand' => 'SoftKatta Solutions',
                'timezone' => 'Asia/Kolkata',
            ],
            'owner_id' => $owner?->id ?? $data['owner_id'] ?? null,
        ]);
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        $tenant->update($data);

        return $tenant->fresh();
    }
}
