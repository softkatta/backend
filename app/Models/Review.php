<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_PRODUCT = 'product';

    public const TYPE_SERVICE = 'service';

    protected $fillable = [
        'uuid',
        'review_type',
        'product_id',
        'service_id',
        'full_name',
        'company_name',
        'email',
        'mobile',
        'city',
        'country',
        'rating',
        'title',
        'description',
        'profile_image',
        'screenshot',
        'would_recommend',
        'consent_at',
        'status',
        'is_featured',
        'is_verified',
        'admin_reply',
        'replied_at',
        'helpful_count',
        'report_count',
        'ip_address',
        'user_agent',
        'approved_at',
        'approved_by',
    ];

    protected $appends = [
        'profile_image_url',
        'screenshot_url',
        'target_name',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'would_recommend' => 'boolean',
            'consent_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_verified' => 'boolean',
            'replied_at' => 'datetime',
            'helpful_count' => 'integer',
            'report_count' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Review $review): void {
            if (empty($review->uuid)) {
                $review->uuid = (string) Str::uuid();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)->where('status', self::STATUS_APPROVED);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('review_type', self::TYPE_PRODUCT)->where('product_id', $productId);
    }

    public function scopeForService(Builder $query, int $serviceId): Builder
    {
        return $query->where('review_type', self::TYPE_SERVICE)->where('service_id', $serviceId);
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        return $this->publicUrl($this->profile_image);
    }

    public function getScreenshotUrlAttribute(): ?string
    {
        return $this->publicUrl($this->screenshot);
    }

    public function getTargetNameAttribute(): ?string
    {
        if ($this->review_type === self::TYPE_PRODUCT) {
            return $this->product?->name;
        }

        if ($this->review_type === self::TYPE_SERVICE) {
            return $this->service?->name;
        }

        return null;
    }

    public function toPublicArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'review_type' => $this->review_type,
            'product_id' => $this->product_id,
            'service_id' => $this->service_id,
            'target_name' => $this->target_name,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ] : null,
            'service' => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'slug' => $this->service->slug,
            ] : null,
            'full_name' => $this->full_name,
            'company_name' => $this->company_name,
            'city' => $this->city,
            'country' => $this->country,
            'rating' => $this->rating,
            'title' => $this->title,
            'description' => $this->description,
            'profile_image_url' => $this->profile_image_url,
            'screenshot_url' => $this->screenshot_url,
            'would_recommend' => $this->would_recommend,
            'is_featured' => $this->is_featured,
            'is_verified' => $this->is_verified,
            'admin_reply' => $this->admin_reply,
            'replied_at' => $this->replied_at,
            'helpful_count' => $this->helpful_count,
            'created_at' => $this->created_at,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return str_starts_with($path, '/storage/') || str_starts_with($path, 'http')
                ? $path
                : (str_starts_with($path, '/') ? $path : '/storage/'.$path);
        }

        return '/storage/'.$path;
    }
}
