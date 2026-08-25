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
            'kind' => ['nullable', 'string', 'in:image,video,installer'],
            'folder' => ['nullable', 'string', 'max:64'],
        ]);

        $kind = $data['kind'] ?? 'image';
        $fileRules = match ($kind) {
            'video' => ['required', 'file', 'mimes:mp4,webm,mov,quicktime', 'max:102400'],
            'installer' => ['required', 'file', 'mimes:apk,exe,msi,zip', 'max:524288'],
            default => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg,ico', 'max:10240'],
        };

        $request->validate([
            'file' => $fileRules,
        ]);

        $folder = Str::slug($data['folder'] ?? 'uploads');
        $path = $request->file('file')->store($folder, 'public');

        return $this->success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'kind' => $kind,
        ], 'File uploaded.', 201);
    }
}
