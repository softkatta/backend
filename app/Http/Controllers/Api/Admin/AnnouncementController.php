<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::query()
            ->with('author:id,name,email')
            ->withCount('reads')
            ->latest('published_at')
            ->latest('id');

        if ($request->filled('published')) {
            $query->where('is_published', $request->boolean('published'));
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;

        if (($data['is_published'] ?? false) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $announcement = Announcement::create($data);

        return $this->success(
            $announcement->load('author:id,name,email')->loadCount('reads'),
            'Announcement created.',
            201,
        );
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return $this->success($announcement->load('author:id,name,email')->loadCount('reads'));
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $data = $this->validated($request, updating: true);

        if (array_key_exists('is_published', $data) && $data['is_published'] && ! $announcement->published_at && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $announcement->update($data);

        return $this->success(
            $announcement->fresh()->load('author:id,name,email')->loadCount('reads'),
            'Announcement updated.',
        );
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->permanentlyDelete($announcement);

        return $this->success(null, 'Announcement deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'body' => [$required, 'string', 'max:20000'],
            'priority' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(Announcement::PRIORITIES)],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);
    }
}
