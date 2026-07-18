<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\StoreJobApplicationRequest;
use App\Models\Career;
use App\Services\CareerApplicationService;
use App\Services\RecaptchaService;
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

    public function apply(StoreJobApplicationRequest $request, string $slug, CareerApplicationService $service, RecaptchaService $recaptcha): JsonResponse
    {
        $recaptcha->verify($request->input('recaptcha_token'), $request->ip(), 'career_apply');

        $career = Career::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $application = $service->submit($career, $request->safe()->except(['recaptcha_token']), $request);

        return $this->success($application, 'Your application has been submitted successfully. A confirmation email has been sent.', 201);
    }
}
