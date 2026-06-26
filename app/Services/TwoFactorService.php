<?php

namespace App\Services;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        protected Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeUrl(User $user, string $secret): string
    {
        $issuer = config('app.name', 'SoftKatta');

        return $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);
    }

    public function verify(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return $this->google2fa->verifyKey($user->two_factor_secret, $this->normalizeCode($code), 2);
    }

    public function verifySecret(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $this->normalizeCode($code), 2);
    }

    private function normalizeCode(string $code): string
    {
        return preg_replace('/\D+/', '', $code) ?? '';
    }
}
