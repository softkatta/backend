<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\ChatbotSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotSettingsController extends BaseApiController
{
    public function __construct(private readonly ChatbotSettingsService $settings) {}

    public function show(): JsonResponse
    {
        return $this->success($this->settings->all());
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'welcome_message' => ['sometimes', 'string'],
            'welcome_robot_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'theme_color' => ['sometimes', 'string', 'max:20'],
            'position' => ['sometimes', 'string', 'in:left,right'],
            'auto_open_delay' => ['sometimes', 'integer', 'min:0', 'max:600'],
            'file_upload_enabled' => ['sometimes', 'boolean'],
            'business_hours' => ['sometimes', 'string'],
            'company_name' => ['sometimes', 'string', 'max:190'],
            'company_phone' => ['sometimes', 'string', 'max:30'],
            'company_email' => ['sometimes', 'string', 'max:190'],
            'company_website' => ['sometimes', 'string', 'max:190'],
            'company_address' => ['sometimes', 'string', 'max:500'],
        ]);

        return $this->success($this->settings->update($payload), 'Chatbot settings updated.');
    }
}
