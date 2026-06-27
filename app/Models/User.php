<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'company_name',
        'gst_number',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_email_enabled',
        'two_factor_recovery_codes',
        'security_setup_completed_at',
        'security_setup_skipped_at',
        'login_alerts_enabled',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_enabled' => 'boolean',
            'two_factor_email_enabled' => 'boolean',
            'two_factor_recovery_codes' => 'array',
            'security_setup_completed_at' => 'datetime',
            'security_setup_skipped_at' => 'datetime',
            'login_alerts_enabled' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->two_factor_email_enabled === null) {
                $user->two_factor_email_enabled = true;
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function platformNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class, 'author_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebauthnCredential::class);
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function hasAuthenticatorTwoFactor(): bool
    {
        return (bool) $this->two_factor_enabled && (bool) $this->two_factor_secret;
    }

    public function hasEmailTwoFactor(): bool
    {
        return (bool) $this->two_factor_email_enabled;
    }

    public function hasPasskeyTwoFactor(): bool
    {
        return $this->webauthnCredentials()->exists();
    }

    public function requiresTwoFactorAtLogin(): bool
    {
        return $this->hasAuthenticatorTwoFactor()
            || $this->hasEmailTwoFactor()
            || $this->hasPasskeyTwoFactor();
    }

    public function passkeyOnlyAtLogin(): bool
    {
        return $this->hasPasskeyTwoFactor()
            && ! $this->hasAuthenticatorTwoFactor()
            && ! $this->hasEmailTwoFactor();
    }

    public function twoFactorType(): string
    {
        $methods = [];

        if ($this->hasEmailTwoFactor()) {
            $methods[] = 'email';
        }

        if ($this->hasAuthenticatorTwoFactor()) {
            $methods[] = 'authenticator';
        }

        if ($this->hasPasskeyTwoFactor()) {
            $methods[] = 'passkey';
        }

        if ($methods === []) {
            return 'none';
        }

        if (count($methods) > 1) {
            return 'multiple';
        }

        return $methods[0];
    }

    /**
     * @return list<string>
     */
    public function availableTwoFactorMethods(): array
    {
        /** @var \App\Services\SecurityService $security */
        $security = app(\App\Services\SecurityService::class);

        $methods = [];

        if ($this->hasPasskeyTwoFactor()) {
            $methods[] = 'passkey';
        }

        if ($this->hasAuthenticatorTwoFactor()) {
            $methods[] = 'authenticator';
        }

        if ($this->hasEmailTwoFactor()) {
            $methods[] = 'email';
        }

        $allowedMethods = $security->filterAllowedMethods($methods);

        if ($this->isClient() && $this->hasEmailTwoFactor() && ! in_array('email', $allowedMethods, true)) {
            $allowedMethods[] = 'email';
        }

        return $security->sortMethodsByPriority($allowedMethods);
    }
}
