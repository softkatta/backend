<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_name',
        'browser',
        'platform',
        'ip_address',
        'device_token',
        'last_login_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
