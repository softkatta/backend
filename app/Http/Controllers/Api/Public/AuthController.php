<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Client\UpdateProfileRequest;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\MaintenanceService;
use App\Services\SecurityService;
use App\Services\TenantService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request, TenantService $tenantService, MaintenanceService $maintenance, SecurityService $security): JsonResponse
    {
        if ($maintenance->isEnabled()) {
            return $this->error($maintenance->message(), 503);
        }

        $validated = $request->validated();
        $avatarPath = $request->file('avatar')?->store('avatars', 'public');

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'avatar' => $avatarPath,
            'company_name' => $validated['company'] ?? null,
            'role' => UserRole::Client,
            'two_factor_email_enabled' => $security->twoFactorLoginEnabled(),
            'is_active' => true,
        ]);

        $user->assignRole('client');

        $tenant = $tenantService->create([
            'name' => $validated['company'] ?? $user->name.' Workspace',
        ], $user);

        $user->update(['tenant_id' => $tenant->id]);
        $user->refresh();

        $tokens = AuthSecurityController::issueAuthTokens($user);

        return $this->success([
            'user' => AuthSecurityController::formatUser($user->load(AuthSecurityController::authUserRelations())),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ], 'Registration successful.', 201);
    }

    public function refresh(Request $request, AuthTokenService $authTokens): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $tokens = $authTokens->refresh($data['refresh_token']);

        if (! $tokens) {
            return $this->error('Invalid or expired refresh token.', 401);
        }

        return $this->success($tokens, 'Session refreshed.');
    }

    public function identifyLogin(Request $request, SecurityService $security): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! $user->is_active) {
            return $this->success([
                'found' => false,
                'passkey_only' => false,
                'requires_password' => true,
                'methods' => [],
            ]);
        }

        if ($security->shouldAutoEnableEmailTwoFactorAtLogin($user)) {
            $user->update(['two_factor_email_enabled' => true]);
            $user->refresh();
        }

        $passkeyOnly = $security->requiresTwoFactorAtLogin($user)
            && $user->passkeyOnlyAtLogin()
            && $security->allowPasskeys();
        $challenge = $passkeyOnly ? AuthSecurityController::createLoginChallenge($user) : null;

        return $this->success([
            'found' => true,
            'passkey_only' => $passkeyOnly,
            'requires_password' => ! $passkeyOnly,
            'methods' => $user->availableTwoFactorMethods(),
            'challenge_token' => $challenge['token'] ?? null,
        ]);
    }

    public function login(LoginRequest $request, TenantService $tenantService, MaintenanceService $maintenance, SecurityService $security, TrustedDeviceService $trustedDevices): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            LoginLog::create([
                'user_id' => User::where('email', $request->email)->value('id'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'logged_in_at' => now(),
            ]);

            return $this->error('Invalid credentials.', 422);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return $this->error('Your account has been deactivated.', 403);
        }

        if ($maintenance->isEnabled() && ! $user->isSuperAdmin()) {
            Auth::logout();

            return $this->error('The site is under maintenance. Only administrators can sign in right now.', 503);
        }

        if ($user->isSuperAdmin() && ! $security->ipAllowed($request->ip())) {
            Auth::logout();

            return $this->error('Admin access is not allowed from this IP address.', 403);
        }

        if ($user->isClient() && ! $user->tenant_id) {
            $tenant = $tenantService->create([
                'name' => $user->company_name ?? $user->name.' Workspace',
            ], $user);
            $user->update(['tenant_id' => $tenant->id]);
            $user->refresh();
        }

        if ($security->shouldAutoEnableEmailTwoFactorAtLogin($user)) {
            $user->update(['two_factor_email_enabled' => true]);
            $user->refresh();
        }

        if ($security->requiresTwoFactorAtLogin($user)) {
            $challenge = AuthSecurityController::createLoginChallenge($user);
            Auth::logout();

            return $this->success([
                'requires_2fa' => true,
                'challenge_token' => $challenge['token'],
                'methods' => $challenge['methods'],
            ], 'Two-factor authentication required.');
        }

        $user->update(['last_login_at' => now()]);

        LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
            'logged_in_at' => now(),
        ]);

        $trustedDevices->record($user, $request);

        AuthSecurityController::sendLoginAlert($user, $request);

        $tokens = AuthSecurityController::issueAuthTokens($user);
        Auth::logout();

        return $this->success([
            'user' => AuthSecurityController::formatUser($user->load(AuthSecurityController::authUserRelations())),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(AuthSecurityController::formatUser($request->user()->load(AuthSecurityController::authUserRelations())));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $this->success(
            AuthSecurityController::formatUser($user->fresh()->load(AuthSecurityController::authUserRelations())),
            'Profile updated successfully.',
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        $user->update(['password' => $request->validated('password')]);

        return $this->success(null, 'Password updated successfully.');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return $this->success(
            AuthSecurityController::formatUser($user->fresh()->load(AuthSecurityController::authUserRelations())),
            'Profile photo updated successfully.',
        );
    }
}
