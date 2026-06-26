<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;

class SiteMaintenanceController extends BaseApiController
{
    public function show(MaintenanceService $maintenance): JsonResponse
    {
        return $this->success($maintenance->page());
    }
}
