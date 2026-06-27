<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(ContactMessage::latest()->paginate(20));
    }

    public function show(ContactMessage $message): JsonResponse
    {
        return $this->success($message);
    }

    public function update(Request $request, ContactMessage $message): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:new,read,replied,archived'],
        ]);

        $message->update($data);

        return $this->success($message->fresh(), 'Contact message updated.');
    }

    public function destroy(ContactMessage $message): JsonResponse
    {
        $this->permanentlyDelete($message);

        return $this->success(null, 'Contact message deleted.');
    }
}
