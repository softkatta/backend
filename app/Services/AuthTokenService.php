<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRefreshToken;
use Illuminate\Support\Str;

class AuthTokenService
{
    public function issueTokenPair(User $user): array
    {
        $accessToken = $user->createToken('softkatta-api', ['*'])->plainTextToken;
        $refreshPlain = Str::random(64);

        UserRefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $refreshPlain),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshPlain,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string}|null
     */
    public function refresh(string $refreshToken): ?array
    {
        $refreshToken = trim($refreshToken);

        if ($refreshToken === '') {
            return null;
        }

        $record = UserRefreshToken::query()
            ->where('token_hash', hash('sha256', $refreshToken))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return null;
        }

        $user = $record->user;

        if (! $user || ! $user->is_active) {
            $record->delete();

            return null;
        }

        $record->delete();

        return $this->issueTokenPair($user);
    }
}
