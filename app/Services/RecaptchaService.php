<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecaptchaService
{
    public function isEnabled(): bool
    {
        $enabled = filter_var(
            Setting::query()->where('key', 'recaptcha_enabled')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $enabled) {
            return false;
        }

        return $this->siteKey() !== '' && $this->secretKey() !== '';
    }

    /**
     * @return array{enabled: bool, site_key: string}
     */
    public function publicConfig(): array
    {
        $siteKey = $this->siteKey();
        $enabled = $this->isEnabled();

        return [
            'enabled' => $enabled,
            'site_key' => $enabled ? $siteKey : '',
        ];
    }

    public function verify(?string $token, ?string $ip = null, ?string $expectedAction = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $token) {
            throw ValidationException::withMessages([
                'recaptcha_token' => ['Please complete the captcha challenge.'],
            ]);
        }

        try {
            $response = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $this->secretKey(),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            $ok = $response->json('success') === true;
            $score = (float) ($response->json('score') ?? 1);
            $action = (string) ($response->json('action') ?? '');

            if (! $ok || $score < 0.3) {
                throw ValidationException::withMessages([
                    'recaptcha_token' => ['Captcha verification failed. Please try again.'],
                ]);
            }

            if ($expectedAction && $action !== '' && $action !== $expectedAction) {
                throw ValidationException::withMessages([
                    'recaptcha_token' => ['Captcha verification failed. Please try again.'],
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification error', ['error' => $e->getMessage()]);
            throw ValidationException::withMessages([
                'recaptcha_token' => ['Captcha verification unavailable. Please try again later.'],
            ]);
        }
    }

    public function siteKey(): string
    {
        return trim((string) Setting::query()->where('key', 'recaptcha_site_key')->value('value'));
    }

    public function secretKey(): string
    {
        return trim((string) Setting::query()->where('key', 'recaptcha_secret_key')->value('value'));
    }
}
