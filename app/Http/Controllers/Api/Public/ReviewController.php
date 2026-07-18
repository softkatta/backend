<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\StoreReviewRequest;
use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Services\RecaptchaService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseApiController
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly RecaptchaService $recaptcha,
    ) {
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $review = $this->reviews->submit($request->validated(), $request);

        return $this->success([
            'uuid' => $review->uuid,
            'status' => $review->status,
            'message' => 'Thank you! Your review is pending approval.',
        ], 'Review submitted successfully.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->reviews->publicList($request->only([
            'review_type', 'product_id', 'service_id', 'rating', 'featured', 'sort',
        ]), $request->integer('per_page', 12));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Review $r) => $r->toPublicArray())
        );

        return $this->success($paginator);
    }

    public function featured(Request $request): JsonResponse
    {
        return $this->success($this->reviews->featured($request->integer('limit', 12)));
    }

    public function latest(Request $request): JsonResponse
    {
        return $this->success($this->reviews->latest($request->integer('limit', 8)));
    }

    public function stats(Request $request): JsonResponse
    {
        $scope = $request->string('scope')->toString() ?: null;
        $targetId = $request->filled('target_id') ? $request->integer('target_id') : null;

        return $this->success($this->reviews->stats($scope, $targetId));
    }

    public function productReviews(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $paginator = $this->reviews->productReviews(
            $product,
            $request->integer('per_page', 12),
            $request->filled('rating') ? $request->integer('rating') : null,
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Review $r) => $r->toPublicArray())
        );

        return $this->success([
            'product' => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug],
            'stats' => $this->reviews->stats('product', $product->id),
            'reviews' => $paginator,
        ]);
    }

    public function serviceReviews(Request $request, string $slug): JsonResponse
    {
        $service = Service::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $paginator = $this->reviews->serviceReviews(
            $service,
            $request->integer('per_page', 12),
            $request->filled('rating') ? $request->integer('rating') : null,
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Review $r) => $r->toPublicArray())
        );

        return $this->success([
            'service' => ['id' => $service->id, 'name' => $service->name, 'slug' => $service->slug],
            'stats' => $this->reviews->stats('service', $service->id),
            'reviews' => $paginator,
        ]);
    }

    public function markHelpful(string $uuid): JsonResponse
    {
        $review = Review::query()->where('uuid', $uuid)->approved()->firstOrFail();

        return $this->success([
            'uuid' => $review->uuid,
            'helpful_count' => $this->reviews->markHelpful($review)->helpful_count,
        ], 'Thanks for your feedback.');
    }

    public function report(string $uuid): JsonResponse
    {
        $review = Review::query()->where('uuid', $uuid)->approved()->firstOrFail();

        return $this->success([
            'uuid' => $review->uuid,
            'report_count' => $this->reviews->report($review)->report_count,
        ], 'Review reported. Our team will review it.');
    }

    public function home(Request $request): JsonResponse
    {
        $featuredLimit = min(12, max(1, $request->integer('featured_limit', 8)));

        return $this->success([
            'featured' => $this->reviews->featured($featuredLimit),
            'latest' => [],
            'stats' => $this->reviews->stats(),
        ]);
    }

    public function captchaConfig(): JsonResponse
    {
        return $this->success($this->recaptcha->publicConfig());
    }
}
