<?php

namespace App\Services;

use App\Models\ChatbotSetting;
use Illuminate\Support\Facades\Storage;

class ChatbotSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'welcome_message' => "Welcome to SoftKatta Solutions 👋\n\nHow can I help you today?",
            'welcome_robot_image' => '',
            'theme_color' => '#2563eb',
            'position' => 'right',
            'auto_open_delay' => 0,
            'file_upload_enabled' => false,
            'business_hours' => "Monday – Saturday: 9:00 AM – 7:00 PM\nSunday: Closed",
            'company_name' => 'SoftKatta Solutions',
            'company_phone' => '+91 7038452357',
            'company_email' => 'support@softkatta.in',
            'company_website' => 'https://softkatta.in',
            'company_address' => 'Talni, Nanded, Maharashtra, India',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = ChatbotSetting::query()->pluck('value', 'key')->all();
        $merged = array_merge($this->defaults(), $stored);
        $values = $this->castValues($merged);
        $values['welcome_robot_url'] = $this->resolveWelcomeRobotUrl($values['welcome_robot_image'] ?? '');

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        $settings = $this->all();

        return [
            'enabled' => (bool) ($settings['enabled'] ?? true),
            'welcome_message' => (string) ($settings['welcome_message'] ?? ''),
            'welcome_robot_url' => (string) ($settings['welcome_robot_url'] ?? '/robot.gif'),
            'theme_color' => (string) ($settings['theme_color'] ?? '#2563eb'),
            'position' => (string) ($settings['position'] ?? 'right'),
            'auto_open_delay' => (int) ($settings['auto_open_delay'] ?? 0),
            'file_upload_enabled' => (bool) ($settings['file_upload_enabled'] ?? false),
            'business_hours' => (string) ($settings['business_hours'] ?? ''),
            'company' => [
                'name' => (string) ($settings['company_name'] ?? ''),
                'phone' => (string) ($settings['company_phone'] ?? ''),
                'email' => (string) ($settings['company_email'] ?? ''),
                'website' => (string) ($settings['company_website'] ?? ''),
                'address' => (string) ($settings['company_address'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(array $payload): array
    {
        $allowed = array_keys($this->defaults());

        foreach ($payload as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            ChatbotSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $this->serializeValue($value)],
            );
        }

        return $this->all();
    }

    /**
     * @return list<array{key: string, label: array{en: string, mr: string, hi: string}}>
     */
    public function quickReplyOptions(): array
    {
        return [
            ['key' => 'products', 'label' => ['en' => 'Products', 'mr' => 'उत्पादने', 'hi' => 'उत्पाद']],
            ['key' => 'pricing', 'label' => ['en' => 'Pricing', 'mr' => 'किंमत', 'hi' => 'मूल्य']],
            ['key' => 'book_demo', 'label' => ['en' => 'Book Demo', 'mr' => 'डेमो बुक करा', 'hi' => 'डेमो बुक करें']],
            ['key' => 'contact', 'label' => ['en' => 'Contact Us', 'mr' => 'संपर्क', 'hi' => 'संपर्क करें']],
            ['key' => 'support', 'label' => ['en' => 'Technical Support', 'mr' => 'तांत्रिक मदत', 'hi' => 'तकनीकी सहायता']],
            ['key' => 'business_hours', 'label' => ['en' => 'Business Hours', 'mr' => 'व्यवसाय वेळ', 'hi' => 'कार्य समय']],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function castValues(array $values): array
    {
        $boolKeys = ['enabled', 'file_upload_enabled'];
        $intKeys = ['auto_open_delay'];

        foreach ($boolKeys as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = filter_var($values[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach ($intKeys as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = (int) $values[$key];
            }
        }

        return $values;
    }

    private function serializeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function resolveWelcomeRobotUrl(mixed $path): string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return '/robot.gif';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
