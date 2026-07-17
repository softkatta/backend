<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\HomeSectionsService;
use Illuminate\Http\JsonResponse;

class SiteHomeSectionsController extends BaseApiController
{
    public function __construct(private readonly HomeSectionsService $homeSections)
    {
    }

    public function show(): JsonResponse
    {
        return $this->success($this->homeSections->content());
    }
}
