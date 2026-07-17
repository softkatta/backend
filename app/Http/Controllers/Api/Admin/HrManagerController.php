<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\HrRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class HrManagerController extends BaseApiController
{
    public function store(Request $request, HrRoleService $hrRoles): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $hrRoles->createManager(
            $data['name'],
            $data['email'],
            $data['password'],
        );

        if (filled($data['phone'] ?? null)) {
            $user->update(['phone' => $data['phone']]);
        }

        return $this->success([
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'hr_manager',
                'login_url' => '/hr',
            ],
        ], 'HR manager account created. They can sign in at /hr.', 201);
    }
}
