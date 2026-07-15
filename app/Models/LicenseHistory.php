<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseHistory extends Model
{
    protected $fillable = [
        'license_key_id',
        'event',
        'meta',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function licenseKey(): BelongsTo
    {
        return $this->belongsTo(LicenseKey::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
