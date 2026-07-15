<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'slug',
        'description',
        'price',
        'discount',
        'gst_rate',
        'currency',
        'trial_days',
        'billing_cycle',
        'features',
        'limits',
        'is_popular',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'         => 'decimal:2',
            'discount'      => 'decimal:2',
            'gst_rate'      => 'decimal:2',
            'billing_cycle' => BillingCycle::class,
            'features'      => 'array',
            'limits'        => 'array',
            'is_popular'    => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
