<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly SmtpMailService $smtpMail,
    ) {}

    public function send(User $user, string $purpose): void
    {
        $remaining = $this->resendSecondsRemaining($user, $purpose);
        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'code' => ["Please wait {$remaining} seconds before requesting a new code."],
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $cooldownUntil = now()->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        Cache::put($this->cacheKey($user, $purpose), $code, now()->addMinutes(10));
        Cache::put($this->cooldownCacheKey($user, $purpose), $cooldownUntil->getTimestamp(), $cooldownUntil);

        try {
            $appName = config('app.name', 'SoftKatta');

            $this->smtpMail->send(
                $user->email,
                "[{$appName}] Your verification code",
                sprintf(
                    "Your %s verification code is: %s\n\nThis code expires in 10 minutes. If you did not request this, ignore this email.",
                    $appName,
                    $code,
                ),
            );
        } catch (\Throwable $e) {
            Cache::forget($this->cacheKey($user, $purpose));
            Cache::forget($this->cooldownCacheKey($user, $purpose));
            throw $e;
        }
    }

    public function verify(User $user, string $purpose, string $code): bool
    {
        $expected = Cache::get($this->cacheKey($user, $purpose));

        if (! is_string($expected) || $expected === '') {
            return false;
        }

        $normalized = preg_replace('/\D+/', '', $code) ?? '';

        if ($normalized !== $expected) {
            return false;
        }

        Cache::forget($this->cacheKey($user, $purpose));

        return true;
    }

    private function cacheKey(User $user, string $purpose): string
    {
        return "email_otp:{$purpose}:{$user->id}";
    }

    private function cooldownCacheKey(User $user, string $purpose): string
    {
        return "email_otp_cooldown:{$purpose}:{$user->id}";
    }

    private function resendSecondsRemaining(User $user, string $purpose): int
    {
        $value = Cache::get($this->cooldownCacheKey($user, $purpose));

        if (! is_numeric($value)) {
            return 0;
        }

        $remaining = (int) $value - time();

        return max(0, $remaining);
    }
}
