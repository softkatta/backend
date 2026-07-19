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
        'database_name',
        'status',
        'settings',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
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
     * Normalized SoftKatta-admin domains used for license binding / install checks.
     *
     * @return list<string>
     */
    public function deployDomains(): array
    {
        return collect([$this->frontend_domain, $this->backend_domain, $this->domain])
            ->map(fn ($domain) => LicenseKey::normalizeDomain(is_string($domain) ? $domain : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Both frontend and backend domains must be set before license / project setup.
     */
    public function hasDeployDomains(): bool
    {
        $frontend = LicenseKey::normalizeDomain($this->frontend_domain ?: $this->domain);
        $backend = LicenseKey::normalizeDomain($this->backend_domain);

        return $frontend !== null && $frontend !== '' && $backend !== null && $backend !== '';
    }

    public function allowsDeployDomain(?string $domain): bool
    {
        $normalized = LicenseKey::normalizeDomain($domain);
        if ($normalized === null || $normalized === '') {
            return false;
        }

        return in_array($normalized, $this->deployDomains(), true);
    }
}
