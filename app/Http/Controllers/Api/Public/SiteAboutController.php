<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\AboutPageService;
use Illuminate\Http\JsonResponse;

class SiteAboutController extends BaseApiController
{
    public function show(AboutPageService $about): JsonResponse
    {
        return $this->success($about->content());
    }
}
