<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\ChatbotAnalyticsService;
use Illuminate\Http\JsonResponse;

class ChatbotAnalyticsController extends BaseApiController
{
    public function __construct(private readonly ChatbotAnalyticsService $analytics) {}

    public function index(): JsonResponse
    {
        return $this->success($this->analytics->analytics());
    }
}
