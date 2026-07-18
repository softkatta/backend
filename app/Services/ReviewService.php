<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewService
{
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly RecaptchaService $recaptcha,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, Request $request): Review
    {
        $this->recaptcha->verify($data['recaptcha_token'] ?? null, $request->ip(), 'submit_review');

        $reviewType = $data['review_type'];
        $productId = $reviewType === Review::TYPE_PRODUCT ? (int) $data['product_id'] : null;
        $serviceId = $reviewType === Review::TYPE_SERVICE ? (int) $data['service_id'] : null;

        if ($reviewType === Review::TYPE_PRODUCT) {
            Product::query()->where('is_active', true)->findOrFail($productId);
        } else {
            Service::query()->where('is_active', true)->findOrFail($serviceId);
        }

        $this->assertNoDuplicate($data['email'], $data['mobile'], $reviewType, $productId, $serviceId);

        $profilePath = $this->storeImage($request->file('profile_image'), 'reviews/profiles');
        $screenshotPath = $this->storeImage($request->file('screenshot'), 'reviews/screenshots');

        $review = Review::create([
            'uuid' => (string) Str::uuid(),
            'review_type' => $reviewType,
            'product_id' => $productId,
            'service_id' => $serviceId,
            'full_name' => strip_tags((string) $data['full_name']),
            'company_name' => isset($data['company_name']) ? strip_tags((string) $data['company_name']) : null,
            'email' => strtolower(trim((string) $data['email'])),
            'mobile' => preg_replace('/\s+/', '', (string) $data['mobile']),
            'city' => isset($data['city']) ? strip_tags((string) $data['city']) : null,
            'country' => isset($data['country']) ? strip_tags((string) $data['country']) : 'India',
            'rating' => (int) $data['rating'],
            'title' => strip_tags((string) $data['title']),
            'description' => strip_tags((string) $data['description']),
            'profile_image' => $profilePath,
            'screenshot' => $screenshotPath,
            'would_recommend' => filter_var($data['would_recommend'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'consent_at' => now(),
            'status' => Review::STATUS_PENDING,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
        ]);

        $this->notifyAdmins($review);
        $this->flushStatsCache();

        return $review->load(['product:id,name,slug', 'service:id,name,slug']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function adminList(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Review::query()
            ->with(['product:id,name,slug', 'service:id,name,slug', 'approver:id,name'])
            ->latest();

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function publicList(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Review::query()
            ->approved()
            ->with(['product:id,name,slug', 'service:id,name,slug'])
            ->latest();

        $this->applyPublicFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function featured(int $limit = 12): array
    {
        return Cache::remember($this->cacheKey("featured:{$limit}"), self::CACHE_TTL, function () use ($limit) {
            return Review::query()
                ->featured()
                ->with(['product:id,name,slug', 'service:id,name,slug'])
                ->latest('approved_at')
                ->limit($limit)
                ->get()
                ->map(fn (Review $r) => $r->toPublicArray())
                ->all();
        });
    }

    /**
     * Homepage carousel: all approved reviews, featured first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function carousel(int $limit = 12): array
    {
        return Cache::remember($this->cacheKey("carousel:{$limit}"), self::CACHE_TTL, function () use ($limit) {
            return Review::query()
                ->approved()
                ->with(['product:id,name,slug', 'service:id,name,slug'])
                ->orderByDesc('is_featured')
                ->orderByDesc('approved_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (Review $r) => $r->toPublicArray())
                ->all();
        });
    }

    public function latest(int $limit = 8): array
    {
        return Cache::remember($this->cacheKey("latest:{$limit}"), self::CACHE_TTL, function () use ($limit) {
            return Review::query()
                ->approved()
                ->with(['product:id,name,slug', 'service:id,name,slug'])
                ->latest('approved_at')
                ->limit($limit)
                ->get()
                ->map(fn (Review $r) => $r->toPublicArray())
                ->all();
        });
    }

    public function stats(?string $scope = null, ?int $targetId = null): array
    {
        $cacheKey = $this->cacheKey("stats:{$scope}:{$targetId}");

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($scope, $targetId) {
            $base = Review::query();

            if ($scope === 'product' && $targetId) {
                $base->forProduct($targetId);
            } elseif ($scope === 'service' && $targetId) {
                $base->forService($targetId);
            }

            $approved = (clone $base)->approved();
            $totalApproved = (clone $approved)->count();
            $avg = $totalApproved > 0 ? round((float) (clone $approved)->avg('rating'), 2) : 0.0;

            $distribution = [];
            for ($i = 5; $i >= 1; $i--) {
                $distribution[(string) $i] = (clone $approved)->where('rating', $i)->count();
            }

            return [
                'total' => (clone $base)->count(),
                'pending' => (clone $base)->where('status', Review::STATUS_PENDING)->count(),
                'approved' => $totalApproved,
                'rejected' => (clone $base)->where('status', Review::STATUS_REJECTED)->count(),
                'featured' => (clone $base)->where('is_featured', true)->where('status', Review::STATUS_APPROVED)->count(),
                'average_rating' => $avg,
                'product_reviews' => (clone $base)->where('review_type', Review::TYPE_PRODUCT)->where('status', Review::STATUS_APPROVED)->count(),
                'service_reviews' => (clone $base)->where('review_type', Review::TYPE_SERVICE)->where('status', Review::STATUS_APPROVED)->count(),
                'rating_distribution' => $distribution,
                'recommend_percent' => $totalApproved > 0
                    ? round(((clone $approved)->where('would_recommend', true)->count() / $totalApproved) * 100)
                    : 0,
            ];
        });
    }

    public function productReviews(Product $product, int $perPage = 12, ?int $rating = null): LengthAwarePaginator
    {
        $query = Review::query()
            ->approved()
            ->forProduct($product->id)
            ->with(['product:id,name,slug'])
            ->latest('approved_at');

        if ($rating) {
            $query->where('rating', $rating);
        }

        return $query->paginate($perPage);
    }

    public function serviceReviews(Service $service, int $perPage = 12, ?int $rating = null): LengthAwarePaginator
    {
        $query = Review::query()
            ->approved()
            ->forService($service->id)
            ->with(['service:id,name,slug'])
            ->latest('approved_at');

        if ($rating) {
            $query->where('rating', $rating);
        }

        return $query->paginate($perPage);
    }

    public function approve(Review $review, User $admin): Review
    {
        $review->update([
            'status' => Review::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $this->flushStatsCache();

        return $review->fresh(['product:id,name,slug', 'service:id,name,slug', 'approver:id,name']);
    }

    public function reject(Review $review, User $admin): Review
    {
        $review->update([
            'status' => Review::STATUS_REJECTED,
            'approved_at' => null,
            'approved_by' => $admin->id,
            'is_featured' => false,
        ]);

        $this->flushStatsCache();

        return $review->fresh(['product:id,name,slug', 'service:id,name,slug', 'approver:id,name']);
    }

    public function reply(Review $review, string $reply): Review
    {
        $review->update([
            'admin_reply' => strip_tags($reply),
            'replied_at' => now(),
        ]);

        return $review->fresh(['product:id,name,slug', 'service:id,name,slug']);
    }

    public function setFeatured(Review $review, bool $featured): Review
    {
        if ($featured && $review->status !== Review::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'is_featured' => ['Only approved reviews can be featured.'],
            ]);
        }

        $review->update(['is_featured' => $featured]);
        $this->flushStatsCache();

        return $review->fresh(['product:id,name,slug', 'service:id,name,slug']);
    }

    public function setVerified(Review $review, bool $verified): Review
    {
        $review->update(['is_verified' => $verified]);

        return $review->fresh(['product:id,name,slug', 'service:id,name,slug']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Review $review, array $data): Review
    {
        $payload = [];

        foreach ([
            'full_name', 'company_name', 'email', 'mobile', 'city', 'country',
            'title', 'description', 'admin_reply',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field] !== null ? strip_tags((string) $data[$field]) : null;
            }
        }

        foreach (['rating', 'status', 'would_recommend', 'is_featured', 'is_verified'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (isset($payload['status']) && $payload['status'] === Review::STATUS_APPROVED && ! $review->approved_at) {
            $payload['approved_at'] = now();
        }

        if (isset($payload['admin_reply'])) {
            $payload['replied_at'] = now();
        }

        if (($payload['is_featured'] ?? false) && ($payload['status'] ?? $review->status) !== Review::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'is_featured' => ['Only approved reviews can be featured.'],
            ]);
        }

        $review->update($payload);
        $this->flushStatsCache();

        return $review->fresh(['product:id,name,slug', 'service:id,name,slug', 'approver:id,name']);
    }

    public function delete(Review $review): void
    {
        foreach ([$review->profile_image, $review->screenshot] as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $review->delete();
        $this->flushStatsCache();
    }

    public function markHelpful(Review $review): Review
    {
        $review->increment('helpful_count');

        return $review->fresh();
    }

    public function report(Review $review): Review
    {
        $review->increment('report_count');

        return $review->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $query = Review::query()
            ->with(['product:id,name', 'service:id,name'])
            ->latest();

        $this->applyFilters($query, $filters);

        $filename = 'reviews-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'UUID', 'Type', 'Target', 'Name', 'Company', 'Email', 'Mobile', 'City', 'Country',
                'Rating', 'Title', 'Status', 'Featured', 'Verified', 'Recommend', 'Helpful', 'Reports', 'Created At',
            ]);

            $query->chunk(200, function ($rows) use ($handle): void {
                foreach ($rows as $review) {
                    fputcsv($handle, [
                        $review->uuid,
                        $review->review_type,
                        $review->target_name,
                        $review->full_name,
                        $review->company_name,
                        $review->email,
                        $review->mobile,
                        $review->city,
                        $review->country,
                        $review->rating,
                        $review->title,
                        $review->status,
                        $review->is_featured ? 'yes' : 'no',
                        $review->is_verified ? 'yes' : 'no',
                        $review->would_recommend ? 'yes' : 'no',
                        $review->helpful_count,
                        $review->report_count,
                        optional($review->created_at)?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function flushStatsCache(): void
    {
        $version = (int) Cache::get('reviews:cache_version', 1);
        Cache::forever('reviews:cache_version', $version + 1);
    }

    private function cacheKey(string $suffix): string
    {
        $version = (int) Cache::get('reviews:cache_version', 1);

        return "reviews:v{$version}:{$suffix}";
    }

    private function assertNoDuplicate(string $email, string $mobile, string $type, ?int $productId, ?int $serviceId): void
    {
        $email = strtolower(trim($email));
        $mobile = preg_replace('/\s+/', '', $mobile) ?? $mobile;

        $query = Review::query()
            ->whereIn('status', [Review::STATUS_PENDING, Review::STATUS_APPROVED])
            ->where('review_type', $type);

        if ($type === Review::TYPE_PRODUCT) {
            $query->where('product_id', $productId);
        } else {
            $query->where('service_id', $serviceId);
        }

        $exists = (clone $query)->where(function ($q) use ($email, $mobile): void {
            $q->where('email', $email)->orWhere('mobile', $mobile);
        })->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['A review with this email or mobile already exists for this item.'],
            ]);
        }
    }

    private function storeImage(?UploadedFile $file, string $folder): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store($folder, 'public');
    }

    private function notifyAdmins(Review $review): void
    {
        $admins = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::SuperAdmin)
            ->get();

        $target = $review->target_name ?? $review->review_type;
        $title = 'New review submitted';
        $message = "{$review->full_name} left a {$review->rating}-star {$review->review_type} review for {$target}. Status: pending approval.";

        foreach ($admins as $admin) {
            try {
                $this->notifications->send(
                    $admin,
                    'review_submitted',
                    $title,
                    $message,
                    [NotificationChannel::InApp, NotificationChannel::Email],
                    ['review_id' => $review->id, 'review_uuid' => $review->uuid],
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify admin of review', [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Review>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['rating'])) {
            $query->where('rating', (int) $filters['rating']);
        }
        if (! empty($filters['review_type']) && $filters['review_type'] !== 'all') {
            $query->where('review_type', $filters['review_type']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }
        if (! empty($filters['service_id'])) {
            $query->where('service_id', (int) $filters['service_id']);
        }
        if (isset($filters['featured']) && $filters['featured'] !== '' && $filters['featured'] !== 'all') {
            $query->where('is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('mobile', 'like', $search)
                    ->orWhere('title', 'like', $search)
                    ->orWhere('company_name', 'like', $search);
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Review>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyPublicFilters($query, array $filters): void
    {
        if (! empty($filters['review_type']) && in_array($filters['review_type'], [Review::TYPE_PRODUCT, Review::TYPE_SERVICE], true)) {
            $query->where('review_type', $filters['review_type']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }
        if (! empty($filters['service_id'])) {
            $query->where('service_id', (int) $filters['service_id']);
        }
        if (! empty($filters['rating'])) {
            $query->where('rating', (int) $filters['rating']);
        }
        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }
        if (! empty($filters['sort'])) {
            match ($filters['sort']) {
                'rating_desc' => $query->orderByDesc('rating')->orderByDesc('approved_at'),
                'rating_asc' => $query->orderBy('rating')->orderByDesc('approved_at'),
                'helpful' => $query->orderByDesc('helpful_count')->orderByDesc('approved_at'),
                default => $query->latest('approved_at'),
            };
        }
    }
}
