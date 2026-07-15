<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseApiLog extends Model
{
    protected $fillable = [
        'product_integration_id',
        'license_key_id',
        'endpoint',
        'method',
        'domain',
        'ip',
        'product_slug',
        'success',
        'error_code',
        'status_code',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function productIntegration(): BelongsTo
    {
        return $this->belongsTo(ProductIntegration::class);
    }

    public function licenseKey(): BelongsTo
    {
        return $this->belongsTo(LicenseKey::class);
    }
}
