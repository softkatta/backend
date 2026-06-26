<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class MaintenanceService
{
    public function __construct(
        protected InvoiceProfileService $profile,
    ) {}

    public function isEnabled(): bool
    {
        $value = strtolower(trim((string) $this->setting('maintenance_mode')));

        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function page(): array
    {
        $defaults = $this->defaults();
        $company = $this->profile->company();
        $pageType = $this->pageType();
        $imagePath = $pageType === 'maintenance' ? $this->setting('maintenance_image') : '';

        return [
            'enabled' => $this->isEnabled(),
            'page_type' => $pageType,
            'badge' => $this->setting('maintenance_badge', $defaults['badge']),
            'message' => $this->setting('maintenance_message', $defaults['message']),
            'image_url' => $pageType === 'maintenance' ? $this->storageUrl($imagePath !== '' ? $imagePath : null) : null,
            'logo_url' => $company['logo_url'] ?? null,
            'company_name' => $company['name'] ?? 'SoftKatta Solutions',
            'company_tagline' => $company['tagline'] ?? '',
        ];
    }

    /**
     * @return array{enabled: bool, message: string}
     */
    public function status(): array
    {
        $page = $this->page();

        return [
            'enabled' => $page['enabled'],
            'message' => $page['message'],
        ];
    }

    public function message(): string
    {
        return $this->status()['message'];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'badge' => 'Official Launch Coming Soon',
            'message' => 'We build modern software solutions, websites, mobile applications, ERP systems, CRM platforms, SaaS products, automation tools, and cloud-based business solutions that help organizations grow through technology and digital transformation.',
        ];
    }

    private function pageType(): string
    {
        $value = strtolower(trim($this->setting('maintenance_page_type', 'launch')));

        return $value === 'maintenance' ? 'maintenance' : 'launch';
    }

    private function setting(string $key, ?string $default = null): string
    {
        $value = Setting::query()->where('key', $key)->value('value');

        if ($value === null || $value === '') {
            return $default ?? '';
        }

        return (string) $value;
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
