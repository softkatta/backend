<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request, EmployeePortalService $portal): JsonResponse
    {
        $summary = $portal->dashboardSummary($this->employeeFor($request));

        return $this->success($summary);
    }
}
