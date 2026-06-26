<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\ContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactController extends BaseApiController
{
    public function store(ContactRequest $request): JsonResponse
    {
        $message = ContactMessage::create($request->validated());

        return $this->success($message, 'Thank you for contacting SoftKatta Solutions. We will get back to you soon.', 201);
    }
}
