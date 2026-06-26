<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Mail;

class SmtpMailService
{
    /**
     * @param  array<string, mixed>|null  $overrides
     * @return array{host: string, port: int, username: string, password: string, encryption: ?string, from_address: string, from_name: string}|null
     */
    public function resolveConfig(Integration $integration, ?array $overrides = null): ?array
    {
        $merged = (array) $integration->credentials;

        foreach ($overrides ?? [] as $key => $value) {
            if ($value === null || $value === '' || $value === '••••••••') {
                continue;
            }

            $merged[$key] = $value;
        }

        if (isset($merged['port'])) {
            $merged['port'] = (string) $merged['port'];
        }

        if (($merged['encryption'] ?? '') === 'none') {
            $merged['encryption'] = '';
        }

        $host = $merged['host'] ?? null;
        $username = $merged['username'] ?? null;
        $password = $merged['password'] ?? null;
        $fromAddress = $merged['from_address'] ?? null;

        if (! $host || ! $username || ! $password || ! $fromAddress) {
            return null;
        }

        return [
            'host' => (string) $host,
            'port' => (int) ($merged['port'] ?? 587),
            'username' => (string) $username,
            'password' => (string) $password,
            'encryption' => isset($merged['encryption']) && $merged['encryption'] !== ''
                ? (string) $merged['encryption']
                : null,
            'from_address' => (string) $fromAddress,
            'from_name' => (string) ($merged['from_name'] ?? config('app.name')),
        ];
    }

    /**
     * @param  array{host: string, port: int, username: string, password: string, encryption: ?string, from_address: string, from_name: string}  $smtp
     */
    public function applyConfig(array $smtp): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp['host'],
            'mail.mailers.smtp.port' => $smtp['port'],
            'mail.mailers.smtp.username' => $smtp['username'],
            'mail.mailers.smtp.password' => $smtp['password'],
            'mail.mailers.smtp.encryption' => $smtp['encryption'],
            'mail.from.address' => $smtp['from_address'],
            'mail.from.name' => $smtp['from_name'],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $overrides
     */
    public function sendTest(Integration $integration, string $to, ?array $overrides = null): void
    {
        $appName = config('app.name', 'SoftKatta');
        $body = implode("\n", [
            "This is a test email from {$appName}.",
            '',
            'If you received this message, your SMTP integration is working correctly.',
            '',
            'Sent at: '.now()->toDayDateTimeString(),
        ]);

        $this->sendViaIntegration($integration, $to, "[{$appName}] SMTP test email", $body, $overrides);
    }

    public function send(string $to, string $subject, string $body): void
    {
        $integration = $this->activeIntegration();

        if (! $integration) {
            throw new \RuntimeException('Email (SMTP) integration is not active. Enable it in Admin Settings → Integrations.');
        }

        $this->sendViaIntegration($integration, $to, $subject, $body);
    }

    /**
     * @param  array<string, mixed>|null  $overrides
     */
    public function sendViaIntegration(Integration $integration, string $to, string $subject, string $body, ?array $overrides = null): void
    {
        $smtp = $this->resolveConfig($integration, $overrides);

        if (! $smtp) {
            throw new \RuntimeException('SMTP is not fully configured. Enter host, username, password, and from email.');
        }

        $this->applyConfig($smtp);

        Mail::raw($body, function ($mail) use ($to, $subject, $smtp): void {
            $mail->to($to)
                ->subject($subject)
                ->from($smtp['from_address'], $smtp['from_name']);
        });
    }

    private function activeIntegration(): ?Integration
    {
        return Integration::query()
            ->where('provider', 'email_smtp')
            ->where('is_active', true)
            ->first();
    }
}
