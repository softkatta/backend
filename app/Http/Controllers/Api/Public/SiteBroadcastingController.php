<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\IntegrationCredentialService;
use Illuminate\Http\JsonResponse;

class SiteBroadcastingController extends BaseApiController
{
    public function show(IntegrationCredentialService $integrations): JsonResponse
    {
        $config = $integrations->pusherPublicConfig();

        return $this->success([
            'enabled' => $config !== null,
            ...($config ?? []),
        ]);
    }
}
