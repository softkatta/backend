<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResignationController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function show(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request)->load('exitRecord');

        return $this->success($employee->exitRecord);
    }

    public function store(Request $request, EmployeePortalService $portal): JsonResponse
    {
        $employee = $this->employeeFor($request);
        abort_if($employee->exitRecord, 422, 'Resignation already submitted.');

        $data = $request->validate([
            'resignation_date' => ['required', 'date'],
            'last_working_day' => ['nullable', 'date', 'after_or_equal:resignation_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'resignation_letter' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $exit = $portal->submitResignation($employee, $data, $request->file('resignation_letter'));

        return $this->success($exit->load('employee'), 'Resignation submitted to HR.', 201);
    }
}
