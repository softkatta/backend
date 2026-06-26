<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryCodeService
{
    /**
     * @return list<string> Plain codes (show once to the user).
     */
    public function generate(User $user, int $count = 8): array
    {
        $plain = [];

        for ($i = 0; $i < $count; $i++) {
            $plain[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        $user->update([
            'two_factor_recovery_codes' => array_map(
                fn (string $code) => Hash::make(str_replace('-', '', $code)),
                $plain,
            ),
        ]);

        return $plain;
    }

    public function verify(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes;

        if (! is_array($codes) || $codes === []) {
            return false;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $code) ?? '');

        foreach ($codes as $index => $hashed) {
            if (! is_string($hashed)) {
                continue;
            }

            if (Hash::check($normalized, $hashed) || Hash::check(str_replace('-', '', $normalized), $hashed)) {
                unset($codes[$index]);
                $user->update(['two_factor_recovery_codes' => array_values($codes)]);

                return true;
            }
        }

        return false;
    }

    public function hasCodes(User $user): bool
    {
        return is_array($user->two_factor_recovery_codes) && count($user->two_factor_recovery_codes) > 0;
    }
}
