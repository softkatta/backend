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
            'hero_label' => $this->setting('about_hero_label', $defaults['hero_label']),
            'hero_title' => $this->setting('about_hero_title', $defaults['hero_title']),
            'hero_highlight' => $this->setting('about_hero_highlight', $defaults['hero_highlight']),
            'hero_description' => $this->setting('about_hero_description', $defaults['hero_description']),
            'story_text' => $this->setting('about_story_text', $defaults['story_text']),
            'mission_text' => $this->setting('about_mission_text', $defaults['mission_text']),
            'vision_text' => $this->setting('about_vision_text', $defaults['vision_text']),
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
            'highlight_title' => 'Our Story',
            'highlight_text' => 'SoftKatta Solutions is a software development company based in Talni, Nanded, Maharashtra. We help businesses, educational institutions, healthcare organizations, and startups transform their operations through innovative software solutions. Our mission is to simplify business processes with secure, scalable, and user-friendly technology.',
            'hero_label' => 'About SoftKatta Solutions',
            'hero_title' => 'Building Smart Software Solutions for',
            'hero_highlight' => 'Modern Businesses',
            'hero_description' => 'SoftKatta Solutions is a software development company based in Talni, Nanded, Maharashtra. We help businesses, educational institutions, healthcare organizations, and startups transform their operations through innovative software solutions.',
            'story_text' => "Founded with a vision to make technology accessible for every business, SoftKatta Solutions specializes in developing ERP software, custom business applications, SaaS products, websites, and mobile applications.\n\nWe understand that every business has unique requirements. Instead of providing one-size-fits-all software, we build customized solutions tailored to your business processes. Our team focuses on delivering reliable, secure, and future-ready software that improves efficiency and drives growth.\n\nToday, we proudly serve educational institutes, medical stores, businesses, and organizations across India with innovative digital solutions.",
            'mission_text' => 'To empower businesses through reliable, innovative, and affordable software solutions that simplify operations and accelerate growth.',
            'vision_text' => "To become one of India's most trusted software development companies by delivering world-class digital solutions that create long-term value for businesses.",
            'values' => [
                ['title' => 'Innovation', 'description' => 'Pioneering practical technology solutions for real business challenges.'],
                ['title' => 'Quality', 'description' => 'Delivering reliable, well-tested software that meets professional standards.'],
                ['title' => 'Customer Satisfaction', 'description' => 'Building long-term relationships through responsive support and results.'],
                ['title' => 'Transparency', 'description' => 'Clear communication, honest timelines, and upfront pricing.'],
                ['title' => 'Security', 'description' => 'Protecting your data with secure architecture and best practices.'],
                ['title' => 'Continuous Improvement', 'description' => 'Evolving products and processes to stay ahead.'],
            ],
            'milestones' => [
                ['year' => '2020', 'title' => 'Founded in Nanded', 'description' => 'Started building business management software for local institutions.'],
                ['year' => '2022', 'title' => 'Product portfolio', 'description' => 'Launched Study Point, Medical Store, and Nursery School management software.'],
                ['year' => '2024', 'title' => 'Cloud & SaaS', 'description' => 'Expanded custom development, API integrations, and subscription products.'],
                ['year' => '2026', 'title' => 'Hospital ERP', 'description' => 'Developing comprehensive Hospital Management Software for healthcare providers.'],
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
