<?php

namespace App\Http\Controllers\Api;

use App\Services\HrStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrDocumentController extends BaseApiController
{
    public function download(Request $request, HrStorageService $storage): StreamedResponse
    {
        $token = $request->query('token');
        abort_unless(is_string($token) && $token !== '', 400);

        $resolved = $storage->resolveSignedDownloadToken($token);

        return $storage->downloadResponse($resolved['path'], $resolved['name']);
    }
}
