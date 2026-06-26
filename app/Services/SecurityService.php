<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;

class SecurityService
{
    public function allowEmailOtp(): bool
    {
        return $this->boolSetting('allow_email_otp', true);
    }

    public function allowAuthenticator(): bool
    {
        return $this->boolSetting('allow_authenticator', true);
    }

    public function allowPasskeys(): bool
    {
        return $this->boolSetting('allow_passkeys', true);
    }

    public function enforceTwoFactorForAll(): bool
    {
        return $this->boolSetting('enforce_2fa_all');
    }

    public function allowUsersDisableTwoFactor(): bool
    {
        return $this->boolSetting('allow_users_disable_2fa', true);
    }

    public function enforceTwoFactorForAdmins(): bool
    {
        return $this->boolSetting('enforce_2fa_admins');
    }

    public function enforceTwoFactorForClients(): bool
    {
        return $this->boolSetting('enforce_2fa_clients');
    }

    /**
     * @return list<string>
     */
    public function loginMethodPriority(): array
    {
        $raw = strtolower(trim($this->setting('login_2fa_priority', 'passkey,authenticator,email')));
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $parts === [] ? ['passkey', 'authenticator', 'email'] : $parts;
    }

    /**
     * @return list<string>
     */
    public function enforcedRoles(): array
    {
        $raw = trim($this->setting('enforce_2fa_roles', ''));

        if ($raw === '') {
            return [];
        }

        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function isMethodAllowed(string $method): bool
    {
        return match ($method) {
            'email' => $this->allowEmailOtp(),
            'authenticator' => $this->allowAuthenticator(),
            'passkey' => $this->allowPasskeys(),
            default => false,
        };
    }

    public function enforceTwoFactorForUser(User $user): bool
    {
        if ($this->enforceTwoFactorForAll()) {
            return true;
        }

        $roles = $this->enforcedRoles();
        $roleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        if ($roles !== []) {
            if (in_array($roleValue, $roles, true)) {
                return true;
            }

            return $roleValue === 'super_admin' && in_array('admin', $roles, true);
        }

        if ($user->isSuperAdmin() || in_array($roleValue, ['admin', 'staff'], true)) {
            return $this->enforceTwoFactorForAdmins();
        }

        if ($user->isClient()) {
            return $this->enforceTwoFactorForClients();
        }

        return false;
    }

    public function canUserDisableTwoFactor(User $user): bool
    {
        return $this->allowUsersDisableTwoFactor() && ! $this->mustKeepTwoFactorEnabled($user);
    }

    public function mustKeepTwoFactorEnabled(User $user): bool
    {
        return $this->enforceTwoFactorForUser($user);
    }

    public function canDisableMethod(User $user, string $method): bool
    {
        if (! $this->isMethodAllowed($method)) {
            return false;
        }

        if (! $this->allowUsersDisableTwoFactor()) {
            return false;
        }

        if (! $this->mustKeepTwoFactorEnabled($user)) {
            return true;
        }

        return $this->countEnabledMethods($user) > 1;
    }

    public function countEnabledMethods(User $user): int
    {
        $count = 0;

        if ($user->hasAuthenticatorTwoFactor()) {
            $count++;
        }

        if ($user->hasEmailTwoFactor()) {
            $count++;
        }

        if ($user->hasPasskeyTwoFactor()) {
            $count++;
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    public function filterAllowedMethods(array $methods): array
    {
        return array_values(array_filter(
            $methods,
            fn (string $method) => $this->isMethodAllowed($method),
        ));
    }

    /**
     * @return list<string>
     */
    public function sortMethodsByPriority(array $methods): array
    {
        $priority = $this->loginMethodPriority();
        $rank = array_flip($priority);

        usort($methods, function (string $a, string $b) use ($rank) {
            return ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99);
        });

        return array_values($methods);
    }

    /**
     * @return array<string, mixed>
     */
    public function platformPolicy(): array
    {
        return [
            'allow_email_otp' => $this->allowEmailOtp(),
            'allow_authenticator' => $this->allowAuthenticator(),
            'allow_passkeys' => $this->allowPasskeys(),
            'enforce_2fa_all' => $this->enforceTwoFactorForAll(),
            'enforce_2fa_roles' => $this->enforcedRoles(),
            'enforce_2fa_admins' => $this->enforceTwoFactorForAdmins(),
            'enforce_2fa_clients' => $this->enforceTwoFactorForClients(),
            'allow_users_disable_2fa' => $this->allowUsersDisableTwoFactor(),
            'login_2fa_priority' => $this->loginMethodPriority(),
        ];
    }

    public function ipWhitelistingEnabled(): bool
    {
        return $this->boolSetting('ip_whitelisting');
    }

    public function sessionTimeoutMinutes(): int
    {
        $raw = trim($this->setting('session_timeout_minutes', '30'));

        if ($raw === '') {
            return 0;
        }

        return max(0, (int) $raw);
    }

    public function isTokenIdleExpired(\Laravel\Sanctum\PersonalAccessToken $token): bool
    {
        $timeout = $this->sessionTimeoutMinutes();

        if ($timeout <= 0) {
            return false;
        }

        $lastActivity = $token->last_used_at ?? $token->created_at;

        if (! $lastActivity) {
            return false;
        }

        return $lastActivity->copy()->addMinutes($timeout)->isPast();
    }

    /**
     * @return list<string>
     */
    public function allowedIps(): array
    {
        $raw = $this->setting('ip_whitelist');
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    public function ipAllowed(?string $ip): bool
    {
        if (! $this->ipWhitelistingEnabled()) {
            return true;
        }

        if (! $ip) {
            return false;
        }

        $allowed = $this->allowedIps();

        if ($allowed === []) {
            return in_array($ip, ['127.0.0.1', '::1'], true);
        }

        return in_array($ip, $allowed, true);
    }

    public function userMustSetupTwoFactor(User $user): bool
    {
        if (! $this->enforceTwoFactorForUser($user)) {
            return false;
        }

        return ! $user->requiresTwoFactorAtLogin();
    }

    public function mustBlockForTwoFactorSetup(User $user, string $path): bool
    {
        if (! $this->userMustSetupTwoFactor($user)) {
            return false;
        }

        if ($user->isSuperAdmin() || in_array($user->role instanceof UserRole ? $user->role->value : (string) $user->role, ['admin', 'staff'], true)) {
            return $this->isAdminApiPath($path) && ! $this->isTwoFactorSetupExemptPath($path);
        }

        if ($user->isClient()) {
            return ($this->isClientApiPath($path) || $this->isInboxApiPath($path))
                && ! $this->isSecurityExemptPath($path);
        }

        return false;
    }

    public function isSecurityExemptPath(string $path): bool
    {
        $normalized = trim($path, '/');

        foreach ([
            'api/v1/auth/logout',
            'api/v1/auth/me',
            'api/v1/auth/security',
            'api/v1/auth/security/preferences',
            'api/v1/auth/security/2fa/setup',
            'api/v1/auth/security/2fa/confirm',
            'api/v1/auth/security/2fa/disable',
            'api/v1/auth/security/2fa/email/send',
            'api/v1/auth/security/2fa/email/confirm',
            'api/v1/auth/security/2fa/email/disable/send',
            'api/v1/auth/security/2fa/email/disable',
            'api/v1/auth/security/webauthn/register/options',
            'api/v1/auth/security/webauthn/register/verify',
            'api/v1/auth/security/webauthn/disable',
            'api/v1/auth/security/setup/skip',
            'api/v1/auth/security/sessions',
            'api/v1/auth/security/sessions/revoke',
            'api/v1/auth/2fa/verify',
            'api/v1/auth/2fa/email/send',
            'api/v1/auth/2fa/webauthn/options',
            'api/v1/auth/2fa/webauthn/verify',
            'api/v1/auth/2fa/recovery',
        ] as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function isTwoFactorSetupExemptPath(string $path): bool
    {
        if ($this->isSecurityExemptPath($path)) {
            return true;
        }

        $normalized = trim($path, '/');

        return $normalized === 'api/v1/admin/settings'
            || str_starts_with($normalized, 'api/v1/admin/settings/');
    }

    public function isAdminApiPath(string $path): bool
    {
        return str_contains(trim($path, '/'), 'admin/');
    }

    public function isClientApiPath(string $path): bool
    {
        $normalized = trim($path, '/');

        return $normalized === 'api/v1/client'
            || str_starts_with($normalized, 'api/v1/client/');
    }

    public function isInboxApiPath(string $path): bool
    {
        $normalized = trim($path, '/');

        return $normalized === 'api/v1/inbox'
            || str_starts_with($normalized, 'api/v1/inbox/');
    }

    private function boolSetting(string $key, bool $default = false): bool
    {
        $value = strtolower(trim($this->setting($key)));

        if ($value === '') {
            return $default;
        }

        return in_array($value, ['true', '1', 'yes', 'on'], true);
    }

    private function setting(string $key, ?string $default = null): string
    {
        $value = Setting::query()->where('key', $key)->value('value');

        if ($value === null || $value === '') {
            return $default ?? '';
        }

        return (string) $value;
    }
}
