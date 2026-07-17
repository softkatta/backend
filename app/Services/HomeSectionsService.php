<?php

namespace App\Services;

use App\Models\Setting;

class HomeSectionsService
{
    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        $defaults = $this->defaults();
        $stored = $this->jsonSetting('site_home_sections', []);

        return [
            'demo_video' => array_merge(
                $defaults['demo_video'],
                is_array($stored['demo_video'] ?? null) ? $stored['demo_video'] : [],
            ),
            'technologies' => $this->technologies($stored, $defaults['technologies']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'demo_video' => [
                'label' => 'Product Demo',
                'title' => 'See SoftKatta Solutions',
                'highlight' => 'in Action',
                'description' => 'Watch how Indian businesses manage GST billing, inventory, CRM, payroll, and customer relationships from one secure SoftKatta Solutions cloud platform.',
                'video_url' => '',
                'cta_label' => 'Browse products',
                'cta_href' => '/products',
            ],
            'technologies' => [
                ['name' => 'React', 'description' => 'Modern, fast UI for web & admin dashboards', 'color' => '#61DAFB'],
                ['name' => 'Laravel', 'description' => 'Secure PHP API, queues, and business logic', 'color' => '#FF2D20'],
                ['name' => 'MySQL', 'description' => 'Reliable relational data for billing & CRM', 'color' => '#00758F'],
                ['name' => 'Redis', 'description' => 'Caching and real-time session performance', 'color' => '#DC382D'],
                ['name' => 'AWS Cloud', 'description' => 'Scalable hosting with backups & monitoring', 'color' => '#FF9900'],
                ['name' => 'Docker', 'description' => 'Consistent deploys across environments', 'color' => '#2496ED'],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $default
     * @return array<int, array<string, string>>
     */
    private function technologies(array $stored, array $default): array
    {
        $items = $stored['technologies'] ?? null;

        if (! is_array($items) || count($items) === 0) {
            return $default;
        }

        $mapped = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }

            $mapped[] = [
                'name' => trim((string) $item['name']),
                'description' => trim((string) ($item['description'] ?? '')),
                'color' => trim((string) ($item['color'] ?? '#2563eb')),
            ];
        }

        return count($mapped) > 0 ? $mapped : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonSetting(string $key, array $default): array
    {
        $raw = Setting::query()->where('key', $key)->value('value');

        if ($raw === null || trim((string) $raw) === '') {
            return $default;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : $default;
    }
}
