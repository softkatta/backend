<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Throwable;

class SiteOffersController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $raw = Setting::query()->where('key', 'site_offers')->value('value');

        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : [];
        } else {
            $items = [];
        }

        $now = now();

        $active = collect($items)
            ->filter(fn ($item) => is_array($item) && ($item['active'] ?? true))
            ->filter(function (array $item) use ($now) {
                try {
                    if (! empty($item['start_date']) && Carbon::parse($item['start_date'])->isFuture()) {
                        return false;
                    }
                    if (! empty($item['end_date']) && Carbon::parse($item['end_date'])->isPast()) {
                        return false;
                    }
                } catch (Throwable) {
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
