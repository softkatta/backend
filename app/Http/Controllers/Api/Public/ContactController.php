<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Public\ContactRequest;
use App\Models\ContactMessage;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;

class ContactController extends BaseApiController
{
    public function store(ContactRequest $request, RecaptchaService $recaptcha): JsonResponse
    {
        $recaptcha->verify($request->input('recaptcha_token'), $request->ip(), 'contact');

        $data = $request->safe()->except(['recaptcha_token']);
        $message = ContactMessage::create($data);

        return $this->success($message, 'Thank you for contacting SoftKatta Solutions. We will get back to you soon.', 201);
    }
}
