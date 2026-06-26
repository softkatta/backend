<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\InvoiceProfileService;
use Illuminate\Http\JsonResponse;

class SiteBrandingController extends BaseApiController
{
    public function show(InvoiceProfileService $profile): JsonResponse
    {
        return $this->success($profile->branding());
    }
}
