<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function show(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request)->load(['documents', 'exitRecord', 'user']);

        return $this->success($employee);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'array'],
        ]);

        $employee->update($data);

        if ($request->filled('phone') && $employee->user) {
            $employee->user->update(['phone' => $data['phone']]);
        }

        return $this->success($employee->fresh(['documents', 'exitRecord', 'user']), 'Profile updated.');
    }
}
