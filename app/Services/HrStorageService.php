<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrStorageService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    private const MAX_BYTES = 5_242_880; // 5 MB

    public function storeApplicationDocument(UploadedFile $file, int $applicationId, string $documentType): array
    {
        $this->assertValidFile($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = sprintf('%s-%s.%s', $documentType, Str::uuid(), $extension);
        $path = $file->storeAs("hr/applications/{$applicationId}", $filename, 'local');

        return [
            'document_type' => $documentType,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function storeEmployeeDocument(UploadedFile $file, int $employeeId, string $category): array
    {
        $this->assertValidFile($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = sprintf('%s-%s.%s', $category, Str::uuid(), $extension);
        $path = $file->storeAs("hr/employees/{$employeeId}", $filename, 'local');

        return [
            'category' => $category,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function delete(string $path): void
    {
        if ($path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function deleteEmployeeDirectory(int $employeeId): void
    {
        Storage::disk('local')->deleteDirectory("hr/employees/{$employeeId}");
    }

    public function downloadResponse(string $path, string $originalName, ?string $mimeType = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $originalName, [
            'Content-Type' => $mimeType ?: 'application/octet-stream',
        ]);
    }

    public function createSignedDownloadToken(string $path, string $originalName): string
    {
        return Crypt::encryptString(json_encode([
            'path' => $path,
            'name' => $originalName,
            'exp' => now()->addMinutes(30)->timestamp,
        ]));
    }

    /**
     * @return array{path: string, name: string}
     */
    public function resolveSignedDownloadToken(string $token): array
    {
        $payload = json_decode(Crypt::decryptString($token), true);

        if (! is_array($payload) || empty($payload['path']) || empty($payload['exp']) || $payload['exp'] < now()->timestamp) {
            abort(403, 'Download link expired or invalid.');
        }

        return [
            'path' => (string) $payload['path'],
            'name' => (string) ($payload['name'] ?? 'document'),
        ];
    }

    private function assertValidFile(UploadedFile $file): void
    {
        abort_if($file->getSize() > self::MAX_BYTES, 422, 'File exceeds 5 MB limit.');

        $mime = $file->getMimeType() ?: '';
        abort_unless(in_array($mime, self::ALLOWED_MIMES, true), 422, 'Invalid file type. Allowed: PDF, JPG, JPEG, PNG.');
    }
}
