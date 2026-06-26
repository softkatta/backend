<?php

namespace App\Services;

use App\Models\Setting;

class AboutPageService
{
    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        $defaults = $this->defaults();

        return [
            'highlight_title' => $this->setting('about_highlight_title', $defaults['highlight_title']),
            'highlight_text' => $this->setting('about_highlight_text', $defaults['highlight_text']),
            'story_text' => $this->setting('about_story_text', $defaults['story_text']),
            'values' => $this->jsonSetting('about_values', $defaults['values']),
            'milestones' => $this->jsonSetting('about_milestones', $defaults['milestones']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'highlight_title' => "Made for Bharat's SMEs",
            'highlight_text' => 'We understand GST, Udyam, Shop Act, and the realities of running a business in tier-2 and tier-3 cities — because we build for all of India.',
            'story_text' => 'We help Indian enterprises digitize operations with integrated, affordable, and scalable cloud solutions built for local compliance and global quality.',
            'values' => [
                ['title' => 'Mission', 'description' => 'Empower every Indian SME with affordable, world-class cloud software.'],
                ['title' => 'Vision', 'description' => "India's most trusted SaaS platform for business management."],
                ['title' => 'Values', 'description' => 'Integrity, innovation, customer-first, continuous improvement.'],
                ['title' => 'Team', 'description' => 'Engineers, designers, and support specialists building for Indian businesses.'],
            ],
            'milestones' => [
                ['year' => '2020', 'title' => 'Founded', 'description' => 'Started building business software'],
                ['year' => '2022', 'title' => 'Product expansion', 'description' => 'Added POS & CRM modules'],
                ['year' => '2024', 'title' => 'SaaS platform', 'description' => 'Full multi-product ecosystem'],
                ['year' => '2025', 'title' => 'Growing', 'description' => 'Serving businesses across India'],
            ],
        ];
    }

    private function setting(string $key, string $default): string
    {
        $value = Setting::query()->where('key', $key)->value('value');

        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @param  array<int, array<string, string>>  $default
     * @return array<int, array<string, string>>
     */
    private function jsonSetting(string $key, array $default): array
    {
        $raw = Setting::query()->where('key', $key)->value('value');

        if ($raw === null || trim((string) $raw) === '') {
            return $default;
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return $default;
        }

        $items = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = array_filter([
                'title' => isset($item['title']) ? trim((string) $item['title']) : '',
                'description' => isset($item['description']) ? trim((string) $item['description']) : '',
                'year' => isset($item['year']) ? trim((string) $item['year']) : '',
            ], fn (string $value) => $value !== '');

            if ($normalized !== []) {
                $items[] = $normalized;
            }
        }

        return $items !== [] ? $items : $default;
    }
}
