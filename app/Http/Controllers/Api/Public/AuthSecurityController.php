<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\RecoveryCodeService;
use App\Services\SecurityService;
use App\Services\SmtpMailService;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthSecurityController extends BaseApiController
{
    public function show(Request $request, SecurityService $security, TrustedDeviceService $trustedDevices, RecoveryCodeService $recoveryCodes): JsonResponse
    {
        $user = $request->user()->load('webauthnCredentials');

        $enabledMethods = [];

        if ($user->hasEmailTwoFactor()) {
            $enabledMethods[] = 'email';
        }

        if ($user->hasAuthenticatorTwoFactor()) {
            $enabledMethods[] = 'authenticator';
        }

        if ($user->hasPasskeyTwoFactor()) {
            $enabledMethods[] = 'passkey';
        }

        return $this->success([
            'two_factor_enabled' => $security->requiresTwoFactorAtLogin($user),
            'two_factor_type' => $user->twoFactorType(),
            'methods' => self::formatTwoFactorMethods($user),
            'enabled_methods' => $enabledMethods,
            'platform_policy' => $security->platformPolicy(),
            'can_disable_2fa' => $security->canUserDisableTwoFactor($user),
            'force_two_factor' => $security->mustKeepTwoFactorEnabled($user),
            'can_disable_methods' => [
                'authenticator' => $security->canDisableMethod($user, 'authenticator'),
                'email' => $security->canDisableMethod($user, 'email'),
                'passkey' => $security->canDisableMethod($user, 'passkey'),
            ],
            'enforce_2fa_required' => $security->userMustSetupTwoFactor($user),
            'has_recovery_codes' => $recoveryCodes->hasCodes($user),
            'login_alerts_enabled' => (bool) ($user->login_alerts_enabled ?? true),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'trusted_devices' => $trustedDevices->listForUser($user),
            'security_setup_pending' => ! $user->security_setup_completed_at && ! $user->security_setup_skipped_at,
            'session_timeout_minutes' => $security->sessionTimeoutMinutes(),
        ]);
    }

    public function skipSecuritySetup(Request $request): JsonResponse
    {
        $request->user()->update(['security_setup_skipped_at' => now()]);

        return $this->success(null, 'You can enable security methods anytime from your profile.');
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login_alerts_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->update(['login_alerts_enabled' => $data['login_alerts_enabled']]);

        return $this->success([
            'login_alerts_enabled' => (bool) $user->login_alerts_enabled,
        ], 'Security preferences updated.');
    }

    public function setupTwoFactor(Request $request, TwoFactorService $twoFactor, SecurityService $security): JsonResponse
    {
        if (! $security->allowAuthenticator()) {
            return $this->error('Authenticator app verification is not allowed by your organization.', 422);
        }

        $user = $request->user();
        $secret = $twoFactor->generateSecret();

        Cache::put($this->setupCacheKey($user), $secret, now()->addMinutes(15));

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $twoFactor->qrCodeUrl($user, $secret),
        ], 'Scan the QR code with your authenticator app, then confirm with a code.');
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $twoFactor, SecurityService $security, RecoveryCodeService $recoveryCodes): JsonResponse
    {
        if (! $security->allowAuthenticator()) {
            return $this->error('Authenticator app verification is not allowed by your organization.', 422);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $secret = Cache::get($this->setupCacheKey($user));

        if (! is_string($secret) || $secret === '') {
            return $this->error('Two-factor setup expired. Please start again.', 422);
        }

        if (! $twoFactor->verifySecret($secret, $data['code'])) {
            return $this->error('Invalid authentication code.', 422);
        }

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'security_setup_completed_at' => $user->security_setup_completed_at ?? now(),
        ]);

        Cache::forget($this->setupCacheKey($user));

        $plainRecoveryCodes = $recoveryCodes->generate($user->fresh());

        return $this->success([
            'methods' => self::formatTwoFactorMethods($user->fresh()),
            'recovery_codes' => $plainRecoveryCodes,
        ], 'Authenticator app enabled.');
    }

    public function disableTwoFactor(Request $request, TwoFactorService $twoFactor, SecurityService $security): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $request->user();

        if (! $security->canDisableMethod($user, 'authenticator')) {
            return $this->error('You cannot disable authenticator verification due to security policy. Keep at least one other method enabled while 2FA is required.', 422);
        }

        if (! Hash::check($data['password'], $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        if (! $twoFactor->verify($user, $data['code'])) {
            return $this->error('Invalid authentication code.', 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
        ]);

        return $this->success([
            'methods' => self::formatTwoFactorMethods($user->fresh()),
        ], 'Authenticator app disabled.');
    }

    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => (string) $token->id,
                'name' => $token->name,
                'is_current' => $token->id === $currentTokenId,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ]);

        return $this->success($sessions);
    }

    public function revokeSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return $this->success(null, 'All other sessions have been signed out.');
    }

    public function verifyLogin(Request $request, TwoFactorService $twoFactor, EmailOtpService $emailOtp, RecoveryCodeService $recoveryCodes, SecurityService $security): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'method' => ['required', 'string', 'in:authenticator,email,recovery'],
            'code' => ['required', 'string'],
        ]);

        $user = self::resolveChallengeUser($data['challenge_token']);

        if (! $user || ! $security->requiresTwoFactorAtLogin($user)) {
            Cache::forget(self::challengeCacheKeyPublic($data['challenge_token']));

            return $this->error('Invalid login verification.', 422);
        }

        $verified = match ($data['method']) {
            'authenticator' => $user->hasAuthenticatorTwoFactor() && $twoFactor->verify($user, $data['code']),
            'email' => $user->hasEmailTwoFactor() && $emailOtp->verify($user, 'login:'.$data['challenge_token'], $data['code']),
            'recovery' => $recoveryCodes->verify($user, $data['code']),
            default => false,
        };

        if (! $verified) {
            return $this->error('Invalid authentication code.', 422);
        }

        return self::completeLoginChallenge($user, $data['challenge_token'], $request, $security);
    }

    public static function completeLoginChallenge(User $user, string $challengeToken, Request $request, ?SecurityService $security = null): JsonResponse
    {
        Cache::forget(self::challengeCacheKeyPublic($challengeToken));

        $security ??= app(SecurityService::class);

        if ($user->isSuperAdmin() && ! $security->ipAllowed($request->ip())) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access is not allowed from this IP address.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
            'logged_in_at' => now(),
        ]);

        app(TrustedDeviceService::class)->record($user, $request);

        self::sendLoginAlert($user, $request);

        $token = self::issueAccessToken($user, $security);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => self::formatUser($user->load('tenant', 'roles')),
                'access_token' => $token,
            ],
        ]);
    }

    public static function resolveChallengeUser(string $token): ?User
    {
        $payload = Cache::get(self::challengeCacheKeyPublic($token));
        $userId = is_array($payload) ? ($payload['user_id'] ?? null) : $payload;

        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatTwoFactorMethods(User $user): array
    {
        $user->loadMissing('webauthnCredentials');

        return [
            'authenticator' => [
                'enabled' => $user->hasAuthenticatorTwoFactor(),
            ],
            'email' => [
                'enabled' => $user->hasEmailTwoFactor(),
            ],
            'passkey' => [
                'enabled' => $user->hasPasskeyTwoFactor(),
                'credentials' => $user->webauthnCredentials->map(fn ($credential) => [
                    'id' => (string) $credential->id,
                    'name' => $credential->name,
                    'last_used_at' => $credential->last_used_at?->toIso8601String(),
                    'created_at' => $credential->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ];
    }

    public static function issueAccessToken(User $user, SecurityService $security): string
    {
        return $user->createToken('softkatta-api', ['*'])->plainTextToken;
    }

    public static function sendLoginAlert(User $user, Request $request): void
    {
        if (! ($user->login_alerts_enabled ?? true)) {
            return;
        }

        try {
            $appName = config('app.name', 'SoftKatta');

            app(SmtpMailService::class)->send(
                $user->email,
                "[{$appName}] New sign-in detected",
                sprintf(
                    "A new sign-in to your %s account was detected.\n\nTime: %s\nIP: %s\nDevice: %s",
                    $appName,
                    now()->toDayDateTimeString(),
                    $request->ip() ?? 'unknown',
                    $request->userAgent() ?? 'unknown',
                ),
            );
        } catch (\Throwable) {
            // Do not block login if mail fails.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatUser(User $user): array
    {
        $security = app(SecurityService::class);
        $nameParts = preg_split('/\s+/', trim($user->name), 2) ?: ['', ''];
        $role = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role;

        return [
            'id' => (string) $user->id,
            'email' => $user->email,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'role' => $role === 'super_admin' ? 'admin' : $role,
            'avatar' => $user->avatar,
            'company' => $user->company_name,
            'phone' => $user->phone,
            'is_active' => $user->is_active ?? true,
            'two_factor_enabled' => $security->requiresTwoFactorAtLogin($user),
            'is_demo_account' => $security->isDemoAccount($user),
            'login_alerts_enabled' => (bool) ($user->login_alerts_enabled ?? true),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function setupCacheKey(User $user): string
    {
        return '2fa_setup:'.$user->id;
    }

    private function challengeCacheKey(string $token): string
    {
        return '2fa_challenge:'.$token;
    }

    public static function challengeCacheKeyPublic(string $token): string
    {
        return '2fa_challenge:'.$token;
    }

    public static function createLoginChallenge(User $user): array
    {
        $challenge = bin2hex(random_bytes(32));
        Cache::put('2fa_challenge:'.$challenge, ['user_id' => $user->id], now()->addMinutes(5));

        return [
            'token' => $challenge,
            'methods' => $user->availableTwoFactorMethods(),
        ];
    }
}
