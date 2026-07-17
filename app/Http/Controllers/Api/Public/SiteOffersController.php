<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SiteOffersController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $raw = Setting::query()->where('key', 'site_offers')->value('value');
        $items = json_decode($raw ?? '[]', true);

        if (! is_array($items)) {
            $items = [];
        }

        $now = now();

        $active = collect($items)
            ->filter(fn ($item) => is_array($item) && ($item['active'] ?? true))
            ->filter(function (array $item) use ($now) {
                if (! empty($item['start_date']) && Carbon::parse($item['start_date'])->isFuture()) {
                    return false;
                }
                if (! empty($item['end_date']) && Carbon::parse($item['end_date'])->isPast()) {
                    return false;
                }

                return ! empty($item['text']);
            })
            ->sortBy(fn (array $item) => (int) ($item['priority'] ?? 99))
            ->values()
            ->all();

        return $this->success($active);
    }
}
