<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'name',
        'company',
        'designation',
        'content',
        'avatar',
        'rating',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        if (str_starts_with($this->avatar, '/storage/')) {
            return $this->avatar;
        }

        if (str_starts_with($this->avatar, '/')) {
            return $this->avatar;
        }

        return '/storage/'.$this->avatar;
    }
}
