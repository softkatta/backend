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
     * Domains assigned to a specific subscription (one purchase → one license).
     *
     * @return array{frontend_domain: ?string, backend_domain: ?string, product_id?: int|string|null}|null
     */
    public function subscriptionDomainPair(?Subscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        $pair = data_get($this->settings, 'subscription_domains.'.$subscription->id);
        if (is_array($pair)) {
            return [
                'frontend_domain' => isset($pair['frontend_domain']) ? (string) $pair['frontend_domain'] : null,
                'backend_domain' => isset($pair['backend_domain']) ? (string) $pair['backend_domain'] : null,
                'product_id' => $pair['product_id'] ?? $subscription->product_id,
            ];
        }

        // Legacy: product-slug map (one domain set shared by all purchases of that product).
        return $this->legacyProductDomainPair($subscription->product);
    }

    /**
     * @return array{frontend_domain: ?string, backend_domain: ?string}|null
     */
    protected function legacyProductDomainPair(?Product $product): ?array
    {
        if (! $product) {
            return null;
        }

        $slug = $product->installerSlug();
        $pair = data_get($this->settings, "product_domains.{$slug}");
        if (! is_array($pair)) {
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

    public function hasSubscriptionDomains(?Subscription $subscription): bool
    {
        $pair = $this->subscriptionDomainPair($subscription);
        if (! $pair) {
            return false;
        }

        $frontend = LicenseKey::normalizeDomain($pair['frontend_domain'] ?? null);
        $backend = LicenseKey::normalizeDomain($pair['backend_domain'] ?? null);

        return $frontend !== null && $frontend !== '' && $backend !== null && $backend !== '';
    }

    /**
     * @return list<string>
     */
    public function deployDomains(?Product $product = null, ?Subscription $subscription = null): array
    {
        if ($subscription) {
            $pair = $this->subscriptionDomainPair($subscription);

            return $this->normalizeDomainList([
                $pair['frontend_domain'] ?? null,
                $pair['backend_domain'] ?? null,
            ]);
        }

        if ($product) {
            $domains = collect();
            foreach ((array) data_get($this->settings, 'subscription_domains', []) as $pair) {
                if (! is_array($pair)) {
                    continue;
                }
                if ((int) ($pair['product_id'] ?? 0) !== (int) $product->id) {
                    continue;
                }
                $domains->push($pair['frontend_domain'] ?? null, $pair['backend_domain'] ?? null);
            }

            $legacy = $this->legacyProductDomainPair($product);
            if ($legacy) {
                $domains->push($legacy['frontend_domain'] ?? null, $legacy['backend_domain'] ?? null);
            }

            return $this->normalizeDomainList($domains->all());
        }

        return [];
    }

    public function hasDeployDomains(?Product $product = null, ?Subscription $subscription = null): bool
    {
        if ($subscription) {
            return $this->hasSubscriptionDomains($subscription);
        }

        if ($product) {
            return $this->deployDomains($product) !== [];
        }

        return false;
    }

    public function allowsDeployDomain(?string $domain, ?Product $product = null, ?Subscription $subscription = null): bool
    {
        return $this->matchingDeployDomain($domain, $product, $subscription) !== null;
    }

    /**
     * Return the SoftKatta-assigned domain that matches the request host
     * (exact or SPA/API pair alias).
     */
    public function matchingDeployDomain(?string $domain, ?Product $product = null, ?Subscription $subscription = null): ?string
    {
        $normalized = LicenseKey::normalizeDomain($domain);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        $allowed = $this->deployDomains($product, $subscription);
        if ($allowed === []) {
            return null;
        }

        $matches = [];

        foreach (static::domainMatchCandidates($normalized) as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                $matches[] = $candidate;
            }
        }

        foreach ($allowed as $assigned) {
            if (in_array($normalized, static::domainMatchCandidates($assigned), true)) {
                $matches[] = $assigned;
            }
        }

        $matches = array_values(array_unique($matches));
        if ($matches === []) {
            return null;
        }

        // Prefer SPA host (study-point / kinder) over API host when both are authorized.
        usort($matches, fn (string $a, string $b) => static::spaBindScore($b) <=> static::spaBindScore($a));

        return $matches[0];
    }

    /**
     * Higher score = preferred SoftKatta bound_domain for split SPA/API hosting.
     */
    public static function spaBindScore(string $domain): int
    {
        $domain = strtolower($domain);
        if (str_starts_with($domain, 'study-point.') || str_starts_with($domain, 'kinder.')) {
            return 2;
        }
        if (str_starts_with($domain, 'study-api.') || str_starts_with($domain, 'kinder-api.')) {
            return 0;
        }

        return 1;
    }

    /**
     * SoftKatta split hosting: SPA and API often use paired hosts
     * (study-point.* ↔ study-api.*, kinder.* ↔ kinder-api.*).
     *
     * @return list<string>
     */
    public static function domainMatchCandidates(string $domain): array
    {
        $domain = strtolower($domain);
        $candidates = [$domain];

        if (str_starts_with($domain, 'www.')) {
            $candidates[] = substr($domain, 4);
        } else {
            $candidates[] = 'www.'.$domain;
        }

        $pairs = [
            ['study-point.', 'study-api.'],
            ['study-api.', 'study-point.'],
            ['kinder.', 'kinder-api.'],
            ['kinder-api.', 'kinder.'],
        ];

        foreach ($pairs as [$from, $to]) {
            if (str_starts_with($domain, $from)) {
                $candidates[] = $to.substr($domain, strlen($from));
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @param  list<mixed>  $domains
     * @return list<string>
     */
    protected function normalizeDomainList(array $domains): array
    {
        return collect($domains)
            ->map(fn ($domain) => LicenseKey::normalizeDomain(is_string($domain) ? $domain : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
