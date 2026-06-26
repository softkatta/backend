<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class EmailOtpService
{
    public function __construct(
        private readonly SmtpMailService $smtpMail,
    ) {}

    public function send(User $user, string $purpose): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user, $purpose), $code, now()->addMinutes(10));

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
}
