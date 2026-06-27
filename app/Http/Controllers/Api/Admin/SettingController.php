<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreSettingRequest;
use App\Models\Setting;
use App\Services\InvoiceProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query();

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        return $this->success($query->orderBy('group')->orderBy('key')->get());
    }

    public function store(StoreSettingRequest $request): JsonResponse
    {
        $setting = Setting::create($request->validated());

        return $this->success($setting, 'Setting created.', 201);
    }

    public function update(Request $request, Setting $setting): JsonResponse
    {
        $data = $request->validate([
            'value' => ['nullable', 'string'],
            'group' => ['nullable', 'string', 'in:general,integrations,security,invoice,maintenance,content'],
        ]);

        $setting->update($data);

        return $this->success($setting->fresh(), 'Setting updated.');
    }

    public function destroy(Setting $setting): JsonResponse
    {
        $this->permanentlyDelete($setting);

        return $this->success(null, 'Setting deleted.');
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['nullable', 'string'],
            'settings.*.group' => ['nullable', 'string', 'in:general,integrations,security,invoice,maintenance,content'],
        ]);

        foreach ($data['settings'] as $item) {
            Setting::updateOrCreate(
                ['key' => $item['key']],
                [
                    'value' => $item['value'] ?? '',
                    'group' => $item['group'] ?? 'general',
                ]
            );
        }

        $startSetting = collect($data['settings'])->firstWhere('key', 'invoice_number_start');
        if ($startSetting && ($startSetting['value'] ?? '') !== '') {
            app(InvoiceProfileService::class)->syncInvoiceNumberStart((int) $startSetting['value']);
        }

        return $this->success(null, 'Settings saved.');
    }
}
