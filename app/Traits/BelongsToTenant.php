<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (auth()->check() && auth()->user()->tenant_id && ! auth()->user()->isSuperAdmin()) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    auth()->user()->tenant_id
                );
            }
        });

        static::creating(function ($model): void {
            if (auth()->check() && auth()->user()->tenant_id && empty($model->tenant_id)) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
