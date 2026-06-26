<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Client\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        return $this->success($request->user()->load('tenant'));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->success($user->fresh()->load('tenant'), 'Profile updated successfully.');
    }
}
