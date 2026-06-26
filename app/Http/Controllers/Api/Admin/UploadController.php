<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends BaseApiController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg,ico', 'max:5120'],
            'folder' => ['nullable', 'string', 'max:64'],
        ]);

        $folder = Str::slug($data['folder'] ?? 'uploads');
        $path = $request->file('file')->store($folder, 'public');

        return $this->success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 'File uploaded.', 201);
    }
}
