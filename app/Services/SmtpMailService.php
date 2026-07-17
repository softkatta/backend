<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpMailService
{
    public function __construct(
        private readonly MailTemplateService $templates,
    ) {
    }

    /**
     * @return array{host: string, port: int, username: string, password: string, encryption: ?string, from_address: string, from_name: string}|null
     */
    public function resolveEnvConfig(): ?array
    {
        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        $fromAddress = config('mail.from.address');

        if (! is_string($host) || $host === '' || $host === '127.0.0.1') {
            return null;
        }

        if (! is_string($username) || $username === '' || $username === 'null') {
            return null;
        }

        if (! is_string($password) || $password === '' || $password === 'null') {
            return null;
        }

        if (! is_string($fromAddress) || $fromAddress === '' || $fromAddress === 'hello@example.com') {
            return null;
        }

        $encryption = config('mail.mailers.smtp.encryption');

        return [
            'host' => $host,
            'port' => (int) config('mail.mailers.smtp.port', 587),
            'username' => $username,
            'password' => $password,
            'encryption' => is_string($encryption) && $encryption !== '' && $encryption !== 'none'
                ? $encryption
                : null,
            'from_address' => $fromAddress,
            'from_name' => (string) config('mail.from.name', config('app.name')),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $overrides
     * @return array{host: string, port: int, username: string, password: string, encryption: ?string, from_address: string, from_name: string}|null
     */
    public function resolveConfig(?Integration $integration = null, ?array $overrides = null): ?array
    {
        $merged = [];

        if ($integration) {
            try {
                $merged = (array) $integration->credentials;
            } catch (Throwable) {
                $merged = [];
            }
        }

        $envConfig = $this->resolveEnvConfig();

        if ($envConfig) {
            foreach ($envConfig as $key => $value) {
                if (! isset($merged[$key]) || $merged[$key] === '' || $merged[$key] === null) {
                    $merged[$key] = $value;
                }
            }
        }

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
            'from_name' => (string) ($merged['from_name'] ?? $this->templates->displayName()),
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
            'mail.mailers.smtp.timeout' => 60,
            'mail.from.address' => $smtp['from_address'],
            'mail.from.name' => $smtp['from_name'],
        ]);
    }

    /**
     * @param  array{host: string, port: int, username: string, password: string, encryption: ?string, from_address: string, from_name: string}  $smtp
     */
    public function verifyConnection(array $smtp): void
    {
        $this->applyConfig($smtp);

        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        if (method_exists($transport, 'start')) {
            $transport->start();

            if (method_exists($transport, 'stop')) {
                $transport->stop();
            }

            return;
        }

        Mail::raw('SMTP connection verified.', function ($mail) use ($smtp): void {
            $mail->to($smtp['from_address'])
                ->subject('SMTP verify')
                ->from($smtp['from_address'], $smtp['from_name']);
        });
    }

    /**
     * @param  array<string, mixed>|null  $overrides
     */
    public function sendTest(Integration $integration, string $to, ?array $overrides = null): void
    {
        $body = implode("\n\n", [
            'This is a test email from '.$this->templates->displayName().'.',
            'If you received this message, your SMTP integration is working correctly.',
            'Sent at: '.now()->toDayDateTimeString(),
        ]);

        $this->sendViaIntegration(
            $integration,
            $to,
            $this->templates->formatSubject('SMTP test email'),
            $body,
            'SMTP test email',
            [],
            $overrides,
        );
    }

    /**
     * @param  array<string, string>  $details
     */
    public function send(string $to, string $subject, string $bodyPlain, ?string $title = null, array $details = []): void
    {
        $integration = $this->activeIntegration();
        $smtp = $this->resolveConfig($integration);

        if (! $smtp) {
            throw new \RuntimeException(
                'Email is not configured. Set SMTP in Admin → Settings → Integrations, or add MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, and MAIL_FROM_ADDRESS in backend/.env.'
            );
        }

        $this->sendTemplated($smtp, $to, $subject, $title ?? $subject, $bodyPlain, $details);
    }

    public function sendOtp(string $to, string $subject, string $title, string $code, string $messagePlain): void
    {
        $integration = $this->activeIntegration();
        $smtp = $this->resolveConfig($integration);

        if (! $smtp) {
            throw new \RuntimeException(
                'Email is not configured. Set SMTP in Admin → Settings → Integrations, or add MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, and MAIL_FROM_ADDRESS in backend/.env.'
            );
        }

        $this->applyConfig($smtp);

        $subject = $this->templates->formatSubject($title);

        $html = $this->templates->renderOtp(
            $title,
            $code,
            $this->templates->plainTextToHtml($messagePlain),
        );

        Mail::send([], [], function ($message) use ($to, $subject, $messagePlain, $html, $smtp): void {
            $message->to($to)
                ->subject($subject)
                ->from($smtp['from_address'], $smtp['from_name'])
                ->html($html)
                ->text($messagePlain);
        });
    }

    /**
     * @param  array<string, string>  $details
     * @param  array<string, mixed>|null  $overrides
     */
    public function sendViaIntegration(
        Integration $integration,
        string $to,
        string $subject,
        string $bodyPlain,
        ?string $title = null,
        array $details = [],
        ?array $overrides = null,
    ): void {
        $smtp = $this->resolveConfig($integration, $overrides);

        if (! $smtp) {
            throw new \RuntimeException('SMTP is not fully configured. Enter host, username, password, and from email, then click Save.');
        }

        $this->sendTemplated($smtp, $to, $subject, $title ?? $subject, $bodyPlain, $details);
    }

    /**
     * @param  array{host: string, port: int, username: string, password: string, encryption: ?string, from_address: string, from_name: string}  $smtp
     * @param  array<string, string>  $details
     */
    private function sendTemplated(array $smtp, string $to, string $subject, string $title, string $bodyPlain, array $details = []): void
    {
        $this->applyConfig($smtp);

        $subject = $this->templates->formatSubject($title);

        $html = $this->templates->renderStandard(
            $title,
            $this->templates->plainTextToHtml($bodyPlain),
            $details,
        );

        Mail::send([], [], function ($message) use ($to, $subject, $bodyPlain, $html, $smtp): void {
            $message->to($to)
                ->subject($subject)
                ->from($smtp['from_address'], $smtp['from_name'])
                ->html($html)
                ->text($bodyPlain);
        });
    }

    public function formatFailureMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'SMTP is not fully configured') || str_contains($message, 'Email is not configured')) {
            return $message;
        }

        if (str_contains($message, '535') || str_contains($message, 'BadCredentials') || str_contains($message, 'Username and Password not accepted')) {
            return 'SMTP login failed. Check host, username, and password. Use hosting email (cPanel) or Brevo SMTP key — no Google 2-Step Verification needed for those providers.';
        }

        if (str_contains($message, 'Connection could not be established') || str_contains($message, 'Connection refused')) {
            return 'Could not connect to the SMTP server. Check host, port, and encryption (try port 587 with TLS).';
        }

        if (strlen($message) > 280) {
            return 'Failed to send test email. Check SMTP host, username, password, and from email.';
        }

        return 'Failed to send test email: '.$message;
    }

    private function activeIntegration(): ?Integration
    {
        return Integration::query()
            ->where('provider', 'email_smtp')
            ->where('is_active', true)
            ->first();
    }
}
