<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $this->employeeFor($request);
        $userId = (int) $request->user()->id;

        $announcements = Announcement::query()
            ->visibleToEmployees()
            ->with('author:id,name')
            ->latest('published_at')
            ->latest('id')
            ->paginate(20);

        $readIds = AnnouncementRead::query()
            ->where('user_id', $userId)
            ->whereIn('announcement_id', collect($announcements->items())->pluck('id'))
            ->pluck('announcement_id')
            ->all();

        $announcements->getCollection()->transform(function (Announcement $item) use ($readIds) {
            $item->setAttribute('is_read', in_array($item->id, $readIds, true));

            return $item;
        });

        return $this->success($announcements);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $this->employeeFor($request);
        abort_unless(
            Announcement::query()->visibleToEmployees()->whereKey($announcement->id)->exists(),
            404,
        );

        $userId = (int) $request->user()->id;
        AnnouncementRead::query()->firstOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $userId,
            ],
            ['read_at' => now()],
        );

        $announcement->load('author:id,name');
        $announcement->setAttribute('is_read', true);

        return $this->success($announcement);
    }

    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        $this->employeeFor($request);
        abort_unless(
            Announcement::query()->visibleToEmployees()->whereKey($announcement->id)->exists(),
            404,
        );

        AnnouncementRead::query()->updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $request->user()->id,
            ],
            ['read_at' => now()],
        );

        return $this->success(['is_read' => true], 'Marked as read.');
    }
}
