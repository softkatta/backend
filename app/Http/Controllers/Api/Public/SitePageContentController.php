<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\PublicPageContentService;
use Illuminate\Http\JsonResponse;

class SitePageContentController extends BaseApiController
{
    public function show(PublicPageContentService $pages): JsonResponse
    {
        return $this->success($pages->all());
    }
}
