<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'credentials',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => AsEncryptedArrayObject::class,
            'is_active' => 'boolean',
        ];
    }
}
