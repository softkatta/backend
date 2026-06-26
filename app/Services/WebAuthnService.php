<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Support\Facades\Cache;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

class WebAuthnService
{
    public function registrationOptions(User $user): array
    {
        $webauthn = $this->webauthn();
        $excludeIds = $user->webauthnCredentials()
            ->pluck('credential_id')
            ->map(fn (string $id) => base64_decode($id, true))
            ->filter()
            ->values()
            ->all();

        $args = $webauthn->getCreateArgs(
            $this->userHandle($user),
            $this->userName($user),
            $user->name,
            120,
            false,
            'preferred',
            null,
            $excludeIds,
        );

        Cache::put(
            $this->registerChallengeKey($user),
            $this->encodeChallenge($webauthn->getChallenge()->getBinaryString()),
            now()->addMinutes(5),
        );

        return $this->encodeOptions($args);
    }

    public function verifyRegistration(User $user, array $payload, ?string $deviceName = null): WebauthnCredential
    {
        $challenge = $this->pullChallenge($this->registerChallengeKey($user));

        if ($challenge === null) {
            throw new WebAuthnException('Passkey registration expired. Please try again.');
        }

        $clientDataJSON = base64_decode((string) ($payload['clientDataJSON'] ?? ''), true);
        $attestationObject = base64_decode((string) ($payload['attestationObject'] ?? ''), true);

        if ($clientDataJSON === false || $attestationObject === false) {
            throw new WebAuthnException('Invalid passkey registration payload.');
        }

        $data = $this->webauthn()->processCreate($clientDataJSON, $attestationObject, $challenge, false, true, false);

        return WebauthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => base64_encode($data->credentialId),
            'public_key' => base64_encode($data->credentialPublicKey),
            'counter' => 0,
            'name' => $deviceName ?: 'Passkey',
        ]);
    }

    public function authenticationOptions(User $user, ?string $challengeToken = null): array
    {
        $credentials = $user->webauthnCredentials()->get();

        if ($credentials->isEmpty()) {
            throw new WebAuthnException('No passkeys are registered for this account.');
        }

        $webauthn = $this->webauthn();
        $credentialIds = $credentials
            ->map(fn (WebauthnCredential $credential) => base64_decode($credential->credential_id, true))
            ->filter()
            ->values()
            ->all();

        $args = $webauthn->getGetArgs($credentialIds, 120, true, true, true, true, true, 'preferred');

        $cacheKey = $challengeToken
            ? $this->loginChallengeTokenKey($challengeToken)
            : $this->loginChallengeKey($user);

        Cache::put(
            $cacheKey,
            $this->encodeChallenge($webauthn->getChallenge()->getBinaryString()),
            now()->addMinutes(5),
        );

        return $this->encodeOptions($args);
    }

    public function verifyAuthentication(User $user, array $payload, ?string $challengeToken = null): WebauthnCredential
    {
        $cacheKey = $challengeToken
            ? $this->loginChallengeTokenKey($challengeToken)
            : $this->loginChallengeKey($user);

        $challenge = $this->pullChallenge($cacheKey);

        if ($challenge === null) {
            throw new WebAuthnException('Passkey verification expired. Please try again.');
        }

        $credentialId = base64_decode((string) ($payload['id'] ?? ''), true);
        $clientDataJSON = base64_decode((string) ($payload['clientDataJSON'] ?? ''), true);
        $authenticatorData = base64_decode((string) ($payload['authenticatorData'] ?? ''), true);
        $signature = base64_decode((string) ($payload['signature'] ?? ''), true);

        if ($credentialId === false || $clientDataJSON === false || $authenticatorData === false || $signature === false) {
            throw new WebAuthnException('Invalid passkey authentication payload.');
        }

        $credential = $user->webauthnCredentials()
            ->get()
            ->first(function (WebauthnCredential $stored) use ($credentialId) {
                $storedId = base64_decode($stored->credential_id, true);

                return $storedId !== false && hash_equals($storedId, $credentialId);
            });

        if (! $credential) {
            throw new WebAuthnException('Passkey not recognized.');
        }

        $publicKey = base64_decode($credential->public_key, true);

        if ($publicKey === false) {
            throw new WebAuthnException('Stored passkey is invalid.');
        }

        $this->webauthn()->processGet(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            $publicKey,
            $challenge,
            $credential->counter ?: null,
            false,
            true,
        );

        $credential->update([
            'counter' => $credential->counter + 1,
            'last_used_at' => now(),
        ]);

        return $credential;
    }

    private function webauthn(): WebAuthn
    {
        return new WebAuthn(
            (string) config('webauthn.rp_name'),
            (string) config('webauthn.rp_id'),
            ['none', 'packed'],
            true,
        );
    }

    private function userHandle(User $user): string
    {
        return hash('sha256', 'softkatta-user:'.$user->id, true);
    }

    private function userName(User $user): string
    {
        $local = strtolower(preg_replace('/[^a-z0-9]/i', '', strstr($user->email, '@', true) ?: 'user') ?: 'user');

        return substr($local, 0, 32).$user->id;
    }

    private function loginChallengeTokenKey(string $challengeToken): string
    {
        return 'webauthn_login_challenge_token:'.$challengeToken;
    }

    private function registerChallengeKey(User $user): string
    {
        return 'webauthn_register_challenge:'.$user->id;
    }

    private function loginChallengeKey(User $user): string
    {
        return 'webauthn_login_challenge:'.$user->id;
    }

    private function encodeChallenge(string $binaryChallenge): string
    {
        return base64_encode($binaryChallenge);
    }

    private function pullChallenge(string $key): ?string
    {
        $encoded = Cache::pull($key);

        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $binary = base64_decode($encoded, true);

        return $binary === false ? null : $binary;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeOptions(object $args): array
    {
        return json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
