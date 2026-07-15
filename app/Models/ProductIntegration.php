<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductIntegration extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'slug',
        'version',
        'api_base_url',
        'public_api_key',
        'secret_api_key',
        'client_id',
        'client_secret',
        'webhook_secret',
        'supported_versions',
        'status',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'secret_api_key' => 'encrypted',
            'client_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'supported_versions' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'secret_api_key',
        'client_secret',
        'webhook_secret',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(LicenseApiLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function supportsVersion(string $version): bool
    {
        $supported = $this->supported_versions ?? [];

        if ($supported === []) {
            return version_compare($version, $this->version, '>=') || $version === $this->version;
        }

        return in_array($version, $supported, true);
    }
}
