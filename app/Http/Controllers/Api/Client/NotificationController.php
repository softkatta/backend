<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return $this->success($notifications);
    }

    public function markAsRead(Request $request, Notification $notification, NotificationService $service): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success($service->markAsRead($notification));
    }

    public function markAllAsRead(Request $request, NotificationService $service): JsonResponse
    {
        $count = $service->markAllAsRead($request->user());

        return $this->success(['marked' => $count], 'All notifications marked as read.');
    }
}
