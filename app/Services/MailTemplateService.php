<?php

namespace App\Services;

use Illuminate\Support\Facades\View;

class MailTemplateService
{
    public function __construct(
        private readonly InvoiceProfileService $profile,
    ) {
    }

    public function displayName(): string
    {
        return $this->profile->displayName();
    }

    public function formatSubject(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('/^\[\s*\]\s*/', '', $title) ?? $title;

        $name = trim($this->displayName());
        if ($name === '' || $name === 'Laravel') {
            $name = 'SoftKatta Solutions';
        }

        if (preg_match('/^\[[^\]]+\]\s/u', $title)) {
            return $title;
        }

        return "[{$name}] {$title}";
    }

    /**
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        $company = $this->profile->company();
        $colors = config('invoice.colors', []);

        return [
            'companyName' => $this->displayName(),
            'tagline' => $company['tagline'] ?? '',
            'logoUrl' => $company['logo_url'] ?? null,
            'website' => $company['website'] ?? '',
            'supportEmail' => $company['email'] ?? '',
            'phone' => $company['phone'] ?? '',
            'primary' => $colors['primary'] ?? '#1e40af',
            'navy' => $colors['navy'] ?? '#0f2854',
            'teal' => $colors['teal'] ?? '#14b8a6',
            'text' => $colors['text'] ?? '#334155',
            'textMuted' => $colors['text_muted'] ?? '#64748b',
            'border' => $colors['border'] ?? '#e2e8f0',
            'year' => now()->year,
        ];
    }

    public function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R\R+/', trim($text)) ?: [];

        return collect($paragraphs)
            ->map(function (string $paragraph): string {
                $safe = e(trim($paragraph));

                return '<p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#334155;">'
                    .nl2br($safe, false)
                    .'</p>';
            })
            ->join('');
    }

    /**
     * @param  array<string, string>  $details
     */
    public function renderStandard(string $title, string $bodyHtml, array $details = []): string
    {
        return View::make('emails.standard', [
            ...$this->branding(),
            'title' => $title,
            'bodyHtml' => $bodyHtml,
            'details' => $details,
        ])->render();
    }

    public function renderOtp(string $title, string $code, string $messageHtml): string
    {
        return View::make('emails.otp', [
            ...$this->branding(),
            'title' => $title,
            'code' => $code,
            'messageHtml' => $messageHtml,
        ])->render();
    }

}
