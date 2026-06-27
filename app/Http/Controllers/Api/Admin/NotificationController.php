<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            Notification::with('user:id,name,email')
                ->latest()
                ->paginate(20)
        );
    }

    public function store(Request $request, NotificationService $service): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['required', 'string', 'in:info,success,warning,error'],
            'target' => ['required', 'string', 'in:all_clients,all_admins,specific_user'],
            'user_id' => ['nullable', 'required_if:target,specific_user', 'exists:users,id'],
        ]);

        $users = match ($data['target']) {
            'all_clients' => User::where('role', 'client')->where('is_active', true)->get(),
            'all_admins' => User::where('role', 'super_admin')->get(),
            default => User::where('id', $data['user_id'])->get(),
        };

        foreach ($users as $user) {
            $service->send($user, $data['type'], $data['title'], $data['message']);
        }

        return $this->success(['sent' => $users->count()], 'Notifications sent.', 201);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $this->permanentlyDelete($notification);

        return $this->success(null, 'Notification deleted.');
    }
}
