<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Http\Controllers\Api\Public\AuthSecurityController;
use App\Models\WebauthnCredential;
use App\Services\EmailOtpService;
use App\Services\SecurityService;
use App\Services\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use lbuchs\WebAuthn\WebAuthnException;

class TwoFactorMethodController extends BaseApiController
{
    public function sendEmailEnableOtp(Request $request, EmailOtpService $emailOtp, SecurityService $security): JsonResponse
    {
        if (! $security->allowEmailOtp()) {
            return $this->error('Email OTP verification is not allowed by your organization.', 422);
        }

        $user = $request->user();

        if ($user->hasEmailTwoFactor()) {
            return $this->error('Email OTP authentication is already enabled.', 422);
        }

        $emailOtp->send($user, 'enable_email_2fa');

        return $this->success(null, 'Verification code sent to your email address.');
    }

    public function confirmEmailEnable(Request $request, EmailOtpService $emailOtp, SecurityService $security): JsonResponse
    {
        if (! $security->allowEmailOtp()) {
            return $this->error('Email OTP verification is not allowed by your organization.', 422);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $request->user();

        if (! $emailOtp->verify($user, 'enable_email_2fa', $data['code'])) {
            return $this->error('Invalid or expired verification code.', 422);
        }

        $user->update([
            'two_factor_email_enabled' => true,
            'security_setup_completed_at' => $user->security_setup_completed_at ?? now(),
        ]);

        return $this->success([
            'methods' => AuthSecurityController::formatTwoFactorMethods($user->fresh()),
        ], 'Email OTP authentication enabled.');
    }

    public function sendEmailDisableOtp(Request $request, EmailOtpService $emailOtp): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasEmailTwoFactor()) {
            return $this->error('Email OTP authentication is not enabled.', 422);
        }

        $emailOtp->send($user, 'disable_email_2fa');

        return $this->success(null, 'Verification code sent to your email address.');
    }

    public function disableEmail(Request $request, EmailOtpService $emailOtp, SecurityService $security): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $request->user();

        if (! $security->canDisableMethod($user, 'email')) {
            return $this->error('You cannot disable email OTP due to security policy.', 422);
        }

        if (! Hash::check($data['password'], $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        if (! $emailOtp->verify($user, 'disable_email_2fa', $data['code'])) {
            return $this->error('Invalid or expired verification code.', 422);
        }

        $user->update(['two_factor_email_enabled' => false]);

        return $this->success([
            'methods' => AuthSecurityController::formatTwoFactorMethods($user->fresh()),
        ], 'Email OTP authentication disabled.');
    }

    public function passkeyRegisterOptions(Request $request, WebAuthnService $webauthn, SecurityService $security): JsonResponse
    {
        if (! $security->allowPasskeys()) {
            return $this->error('Passkeys are not allowed by your organization.', 422);
        }

        try {
            return $this->success($webauthn->registrationOptions($request->user()));
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function passkeyRegisterVerify(Request $request, WebAuthnService $webauthn, SecurityService $security): JsonResponse
    {
        if (! $security->allowPasskeys()) {
            return $this->error('Passkeys are not allowed by your organization.', 422);
        }

        $data = $request->validate([
            'clientDataJSON' => ['required', 'string'],
            'attestationObject' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $webauthn->verifyRegistration($request->user(), $data, $data['device_name'] ?? null);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $user = $request->user();
        $user->update([
            'security_setup_completed_at' => $user->security_setup_completed_at ?? now(),
        ]);

        return $this->success([
            'methods' => AuthSecurityController::formatTwoFactorMethods($user->fresh()),
        ], 'Passkey registered successfully.');
    }

    public function deletePasskey(Request $request, WebauthnCredential $credential, SecurityService $security): JsonResponse
    {
        $user = $request->user();

        if ($credential->user_id !== $user->id) {
            return $this->error('Passkey not found.', 404);
        }

        if (! $security->canDisableMethod($user, 'passkey')) {
            return $this->error('You cannot remove this passkey due to security policy.', 422);
        }

        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        $credential->delete();

        return $this->success([
            'methods' => AuthSecurityController::formatTwoFactorMethods($user->fresh()),
        ], 'Passkey removed.');
    }

    public function disableAllPasskeys(Request $request, SecurityService $security): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPasskeyTwoFactor()) {
            return $this->error('Passkeys / WebAuthn is not enabled.', 422);
        }

        if (! $security->canDisableMethod($user, 'passkey')) {
            return $this->error('You cannot disable passkeys due to security policy.', 422);
        }

        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        $user->webauthnCredentials()->delete();

        return $this->success([
            'methods' => AuthSecurityController::formatTwoFactorMethods($user->fresh()),
        ], 'Passkeys / WebAuthn disabled.');
    }

    public function sendLoginEmailOtp(Request $request, EmailOtpService $emailOtp): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
        ]);

        $user = AuthSecurityController::resolveChallengeUser($data['challenge_token']);

        if (! $user || ! $user->hasEmailTwoFactor()) {
            return $this->error('Email OTP verification is not available.', 422);
        }

        $emailOtp->send($user, 'login:'.$data['challenge_token']);

        return $this->success(null, 'Verification code sent to your email address.');
    }

    public function passkeyLoginOptions(Request $request, WebAuthnService $webauthn): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
        ]);

        $user = AuthSecurityController::resolveChallengeUser($data['challenge_token']);

        if (! $user || ! $user->hasPasskeyTwoFactor()) {
            return $this->error('Passkey verification is not available.', 422);
        }

        try {
            return $this->success($webauthn->authenticationOptions($user, $data['challenge_token']));
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function passkeyLoginVerify(Request $request, WebAuthnService $webauthn): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'id' => ['required', 'string'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'userHandle' => ['nullable', 'string'],
        ]);

        $user = AuthSecurityController::resolveChallengeUser($data['challenge_token']);

        if (! $user || ! $user->hasPasskeyTwoFactor()) {
            return $this->error('Login verification expired. Please sign in again.', 422);
        }

        try {
            $webauthn->verifyAuthentication($user, $data, $data['challenge_token']);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return AuthSecurityController::completeLoginChallenge($user, $data['challenge_token'], $request);
    }

    public function passkeyPrimaryLoginOptions(Request $request, WebAuthnService $webauthn, SecurityService $security): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! $user->is_active || ! $user->passkeyOnlyAtLogin() || ! $security->allowPasskeys()) {
            return $this->error('Passkey sign-in is not available for this account.', 422);
        }

        if ($user->isSuperAdmin() && ! $security->ipAllowed($request->ip())) {
            return $this->error('Admin access is not allowed from this IP address.', 403);
        }

        $challenge = AuthSecurityController::createLoginChallenge($user);

        try {
            $options = $webauthn->authenticationOptions($user, $challenge['token']);

            return $this->success([
                'challenge_token' => $challenge['token'],
                ...$options,
            ]);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function passkeyPrimaryLoginVerify(Request $request, WebAuthnService $webauthn): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'id' => ['required', 'string'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'userHandle' => ['nullable', 'string'],
        ]);

        $user = AuthSecurityController::resolveChallengeUser($data['challenge_token']);

        if (! $user || ! $user->passkeyOnlyAtLogin()) {
            return $this->error('Passkey sign-in is not available for this account.', 422);
        }

        try {
            $webauthn->verifyAuthentication($user, $data, $data['challenge_token']);
        } catch (WebAuthnException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return AuthSecurityController::completeLoginChallenge($user, $data['challenge_token'], $request);
    }
}
