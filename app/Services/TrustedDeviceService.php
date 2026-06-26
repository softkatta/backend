<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public function record(User $user, Request $request): TrustedDevice
    {
        $parsed = $this->parseUserAgent($request->userAgent());

        return TrustedDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_token' => $this->deviceFingerprint($user, $request),
            ],
            [
                'device_name' => $parsed['device_name'],
                'browser' => $parsed['browser'],
                'platform' => $parsed['platform'],
                'ip_address' => $request->ip(),
                'last_login_at' => now(),
                'expires_at' => now()->addDays(90),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(User $user): array
    {
        return $user->trustedDevices()
            ->orderByDesc('last_login_at')
            ->get()
            ->map(fn (TrustedDevice $device) => [
                'id' => (string) $device->id,
                'device_name' => $device->device_name,
                'browser' => $device->browser,
                'platform' => $device->platform,
                'ip_address' => $device->ip_address,
                'last_login_at' => $device->last_login_at?->toIso8601String(),
                'expires_at' => $device->expires_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function deviceFingerprint(User $user, Request $request): string
    {
        return hash('sha256', implode('|', [
            $user->id,
            $request->userAgent() ?? 'unknown',
            $request->ip() ?? 'unknown',
        ]));
    }

    /**
     * @return array{device_name: string, browser: string|null, platform: string|null}
     */
    private function parseUserAgent(?string $userAgent): array
    {
        $ua = strtolower($userAgent ?? '');

        $browser = match (true) {
            str_contains($ua, 'edg/') => 'Edge',
            str_contains($ua, 'chrome/') => 'Chrome',
            str_contains($ua, 'firefox/') => 'Firefox',
            str_contains($ua, 'safari/') && ! str_contains($ua, 'chrome/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'iOS',
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'linux') => 'Linux',
            default => 'Device',
        };

        return [
            'device_name' => trim($browser.' '.$platform),
            'browser' => $browser,
            'platform' => $platform,
        ];
    }
}
