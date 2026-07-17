<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseKey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subscription_id',
        'product_id',
        'user_id',
        'license_key',
        'allowed_domains',
        'registered_ip',
        'product_version',
        'max_devices',
        'max_domains',
        'status',
        'is_product_active',
        'activated_at',
        'deactivated_at',
        'expires_at',
        'activation_count',
        'last_verified_at',
        'force_logout_at',
        'suspended_at',
        'revoked_at',
        'revoke_reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status'           => LicenseStatus::class,
            'allowed_domains'  => 'array',
            'meta'             => 'array',
            'is_product_active' => 'boolean',
            'activated_at'     => 'datetime',
            'deactivated_at'   => 'datetime',
            'expires_at'       => 'datetime',
            'last_verified_at' => 'datetime',
            'force_logout_at'  => 'datetime',
            'suspended_at'     => 'datetime',
            'revoked_at'       => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LicenseApiLog::class);
    }

    public function histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LicenseHistory::class);
    }

    public function domainResetRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LicenseDomainResetRequest::class);
    }

    public function installations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LicenseInstallation::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDomainAllowed(?string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);

        if ($domain === null || $domain === '') {
            return false;
        }

        $allowed = collect($this->allowed_domains ?? [])
            ->map(fn ($item) => $this->normalizeDomain($item))
            ->filter()
            ->values()
            ->all();

        if ($allowed === []) {
            return false;
        }

        return in_array($domain, $allowed, true);
    }

    public function hasRegisteredDomains(): bool
    {
        return ! empty($this->allowed_domains);
    }

    public static function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');
        $host = explode('/', $domain)[0] ?: null;

        if ($host === null || $host === '') {
            return null;
        }

        // Ignore ports (React/Laravel may run on different ports in local).
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = explode(':', $host)[0];
        }

        return $host ?: null;
    }
}
