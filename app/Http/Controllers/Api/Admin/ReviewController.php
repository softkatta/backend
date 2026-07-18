<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AdminReplyReviewRequest;
use App\Http\Requests\Admin\AdminUpdateReviewRequest;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends BaseApiController
{
    public function __construct(
        private readonly ReviewService $reviews,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->reviews->adminList(
            $request->only([
                'status', 'rating', 'review_type', 'product_id', 'service_id',
                'featured', 'date_from', 'date_to', 'search',
            ]),
            $request->integer('per_page', 20),
        ));
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->reviews->stats());
    }

    public function show(Review $review): JsonResponse
    {
        return $this->success($review->load(['product:id,name,slug', 'service:id,name,slug', 'approver:id,name']));
    }

    public function update(AdminUpdateReviewRequest $request, Review $review): JsonResponse
    {
        return $this->success(
            $this->reviews->update($review, $request->validated()),
            'Review updated.',
        );
    }

    public function destroy(Review $review): JsonResponse
    {
        $this->reviews->delete($review);

        return $this->success(null, 'Review deleted.');
    }

    public function approve(Request $request, Review $review): JsonResponse
    {
        return $this->success(
            $this->reviews->approve($review, $request->user()),
            'Review approved.',
        );
    }

    public function reject(Request $request, Review $review): JsonResponse
    {
        return $this->success(
            $this->reviews->reject($review, $request->user()),
            'Review rejected.',
        );
    }

    public function reply(AdminReplyReviewRequest $request, Review $review): JsonResponse
    {
        return $this->success(
            $this->reviews->reply($review, $request->validated('admin_reply')),
            'Reply saved.',
        );
    }

    public function feature(Request $request, Review $review): JsonResponse
    {
        $featured = $request->boolean('is_featured', ! $review->is_featured);

        return $this->success(
            $this->reviews->setFeatured($review, $featured),
            $featured ? 'Review marked as featured.' : 'Review unmarked as featured.',
        );
    }

    public function verify(Request $request, Review $review): JsonResponse
    {
        $verified = $request->boolean('is_verified', ! $review->is_verified);

        return $this->success(
            $this->reviews->setVerified($review, $verified),
            $verified ? 'Review marked as verified.' : 'Verified badge removed.',
        );
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->reviews->exportCsv($request->only([
            'status', 'rating', 'review_type', 'product_id', 'service_id',
            'featured', 'date_from', 'date_to', 'search',
        ]));
    }
}
