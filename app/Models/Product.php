<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'overview',
        'logo',
        'banner',
        'login_url',
        'is_active',
        'has_free_trial',
        'trial_days',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_free_trial' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(ProductFeature::class);
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(ProductScreenshot::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideo::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function productIntegration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductIntegration::class);
    }

    public function installerSlug(): string
    {
        if (! empty($this->meta['installer_slug'])) {
            return (string) $this->meta['installer_slug'];
        }

        return match ($this->slug) {
            'study-point-erp', 'study-point' => 'study-point-management-software',
            'kindergarten', 'nursery-school', 'nursery-school-erp' => 'nursery-school-management-software',
            'coaching-erp' => 'coaching',
            'library-management-system' => 'library',
            'gym-management-system' => 'gym',
            default => (string) $this->slug,
        };
    }

    public function currentVersion(): string
    {
        return (string) ($this->meta['current_version'] ?? '1.0.0');
    }
}
