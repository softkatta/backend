<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LicenseInstallation extends Model
{
    protected $fillable = [
        'license_key_id',
        'installation_id',
        'domain',
        'server_fingerprint',
        'install_token_hash',
        'refresh_token_hash',
        'install_token_expires_at',
        'refresh_token_expires_at',
        'product_version',
        'registered_ip',
        'last_verified_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'install_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function licenseKey(): BelongsTo
    {
        return $this->belongsTo(LicenseKey::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isInstallTokenExpired(): bool
    {
        return $this->install_token_expires_at !== null && $this->install_token_expires_at->isPast();
    }

    public function isRefreshTokenExpired(): bool
    {
        return $this->refresh_token_expires_at !== null && $this->refresh_token_expires_at->isPast();
    }

    public function matchesInstallToken(string $token): bool
    {
        return hash_equals($this->install_token_hash, hash('sha256', $token));
    }

    public function matchesRefreshToken(string $token): bool
    {
        return hash_equals($this->refresh_token_hash, hash('sha256', $token));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function generateInstallationId(): string
    {
        return (string) Str::uuid();
    }
}
