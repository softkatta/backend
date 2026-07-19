<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'backend_domain',
        'frontend_domain',
        'extra_domains',
        'database_name',
        'status',
        'settings',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'extra_domains' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Product-scoped SoftKatta Admin domains (Study Point vs Kindergarten, etc.).
     *
     * @return array{frontend_domain: ?string, backend_domain: ?string}|null
     */
    public function productDomainPair(?Product $product): ?array
    {
        if (! $product) {
            return null;
        }

        $slug = $product->installerSlug();
        $pair = data_get($this->settings, "product_domains.{$slug}");

        if (! is_array($pair)) {
            // Also try catalog slug aliases.
            $pair = data_get($this->settings, "product_domains.{$product->slug}");
        }

        if (! is_array($pair)) {
            return null;
        }

        return [
            'frontend_domain' => isset($pair['frontend_domain']) ? (string) $pair['frontend_domain'] : null,
            'backend_domain' => isset($pair['backend_domain']) ? (string) $pair['backend_domain'] : null,
        ];
    }

    public function hasProductDomains(?Product $product): bool
    {
        $pair = $this->productDomainPair($product);
        if (! $pair) {
            return false;
        }

        $frontend = LicenseKey::normalizeDomain($pair['frontend_domain'] ?? null);
        $backend = LicenseKey::normalizeDomain($pair['backend_domain'] ?? null);

        return $frontend !== null && $frontend !== '' && $backend !== null && $backend !== '';
    }

    /**
     * Domains allowed for license bind / install wizard.
     * When $product is given, prefer that product's SoftKatta Admin domains
     * so Study Point domains are not used for Kindergarten (and vice versa).
     *
     * @return list<string>
     */
    public function deployDomains(?Product $product = null): array
    {
        $domains = collect();

        if ($product && $this->hasProductDomains($product)) {
            $pair = $this->productDomainPair($product);
            $domains = $domains->merge([
                $pair['frontend_domain'] ?? null,
                $pair['backend_domain'] ?? null,
            ]);
        } else {
            $domains = $domains->merge([
                $this->frontend_domain,
                $this->backend_domain,
                $this->domain,
            ]);
        }

        foreach ($this->extra_domains ?? [] as $extra) {
            $domains->push($extra);
        }

        return $domains
            ->map(fn ($domain) => LicenseKey::normalizeDomain(is_string($domain) ? $domain : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Product-scoped domains if configured; otherwise workspace frontend+backend.
     */
    public function hasDeployDomains(?Product $product = null): bool
    {
        if ($product && $this->hasProductDomains($product)) {
            return true;
        }

        $frontend = LicenseKey::normalizeDomain($this->frontend_domain ?: $this->domain);
        $backend = LicenseKey::normalizeDomain($this->backend_domain);

        return $frontend !== null && $frontend !== '' && $backend !== null && $backend !== '';
    }

    public function allowsDeployDomain(?string $domain, ?Product $product = null): bool
    {
        $normalized = LicenseKey::normalizeDomain($domain);
        if ($normalized === null || $normalized === '') {
            return false;
        }

        return in_array($normalized, $this->deployDomains($product), true);
    }
}
