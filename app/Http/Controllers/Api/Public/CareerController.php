<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\StoreJobApplicationRequest;
use App\Models\Career;
use App\Services\CareerApplicationService;
use Illuminate\Http\JsonResponse;

class CareerController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $careers = Career::where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->paginate(12);

        return $this->success($careers);
    }

    public function show(string $slug): JsonResponse
    {
        $career = Career::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return $this->success($career);
    }

    public function apply(StoreJobApplicationRequest $request, string $slug, CareerApplicationService $service): JsonResponse
    {
        $career = Career::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $application = $service->submit($career, $request->validated(), $request);

        return $this->success($application, 'Your application has been submitted successfully. A confirmation email has been sent.', 201);
    }
}
