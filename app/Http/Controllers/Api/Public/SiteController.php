<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\RecaptchaService;
use App\Services\SiteVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends BaseApiController
{
    public function captcha(RecaptchaService $recaptcha): JsonResponse
    {
        return $this->success($recaptcha->publicConfig());
    }

    public function visit(Request $request, SiteVisitService $visits): JsonResponse
    {
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:500'],
            'session_key' => ['nullable', 'string', 'max:128'],
        ]);

        return $this->success(
            $visits->track($request, $data['path'] ?? null),
            'Visit recorded.',
        );
    }
}
