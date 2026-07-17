<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeeAccountService
{
    public function __construct(
        private readonly InvoiceProfileService $profile,
        private readonly SmtpMailService $smtpMail,
        private readonly MailTemplateService $templates,
        private readonly IntegrationCredentialService $integrations,
        private readonly EmployeeRoleService $employeeRoles,
    ) {
    }

    /**
     * @return array{
     *     user: ?User,
     *     temporary_password: ?string,
     *     credentials_emailed: bool,
     *     skipped?: bool,
     *     reason?: string,
     *     login_email?: string
     * }
     */
    public function provisionPortalUser(Employee $employee, ?string $portalEmail = null, bool $notify = true): array
    {
        if ($employee->user_id) {
            $user = User::find($employee->user_id);
            if ($user) {
                $this->employeeRoles->ensureAssigned($user);

                return [
                    'user' => $user,
                    'temporary_password' => null,
                    'credentials_emailed' => false,
                    'login_email' => $user->email,
                ];
            }
        }

        $loginEmail = strtolower(trim($portalEmail ?: $employee->email));
        $this->assertDeliverableEmail($loginEmail);

        $existing = User::query()->where('email', $loginEmail)->first();
        if ($existing) {
            if ($existing->role !== UserRole::Employee) {
                return [
                    'user' => null,
                    'temporary_password' => null,
                    'credentials_emailed' => false,
                    'skipped' => true,
                    'reason' => "Login email {$loginEmail} is already used by a {$existing->role->label()} account. Employee profile was created — enter a different portal login email and create portal access again.",
                    'login_email' => $loginEmail,
                ];
            }

            $alreadyLinked = Employee::query()
                ->where('user_id', $existing->id)
                ->where('id', '!=', $employee->id)
                ->exists();

            if ($alreadyLinked) {
                return [
                    'user' => null,
                    'temporary_password' => null,
                    'credentials_emailed' => false,
                    'skipped' => true,
                    'reason' => "Login email {$loginEmail} is already linked to another employee profile. Use a different portal login email.",
                    'login_email' => $loginEmail,
                ];
            }

            $employee->update(['user_id' => $existing->id]);
            $this->employeeRoles->ensureAssigned($existing);
            $emailed = $notify ? $this->notifyExistingPortalAccess($employee->fresh(), $existing) : false;

            return [
                'user' => $existing,
                'temporary_password' => null,
                'credentials_emailed' => $emailed,
                'login_email' => $existing->email,
            ];
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $user = User::create([
            'name' => $employee->full_name,
            'email' => $loginEmail,
            'phone' => $employee->phone,
            'password' => $temporaryPassword,
            'initial_login_password' => $temporaryPassword,
            'role' => UserRole::Employee,
            'is_active' => true,
            'two_factor_email_enabled' => false,
            'two_factor_enabled' => false,
        ]);

        $this->employeeRoles->ensureAssigned($user);

        $employee->update(['user_id' => $user->id]);

        $emailed = $notify
            ? $this->notifyPortalCredentials($employee->fresh(), $user, $temporaryPassword)
            : false;

        return [
            'user' => $user,
            'temporary_password' => $temporaryPassword,
            'credentials_emailed' => $emailed,
            'login_email' => $loginEmail,
        ];
    }

    public function revokePortalAccess(int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user || $user->role !== UserRole::Employee) {
            return;
        }

        $user->tokens()->delete();
        $user->delete();
    }

    private function employeePortalUrl(): string
    {
        $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

        return "{$frontend}/employee";
    }

    /**
     * @return array{
     *     email: string,
     *     temporary_password: ?string,
     *     sent_email: bool,
     *     sent_whatsapp: bool,
     *     portal_created: bool
     * }
     */
    public function sendPortalLoginDetails(Employee $employee, string $channel, ?string $portalEmail = null): array
    {
        $channel = strtolower(trim($channel));

        if (! in_array($channel, ['email', 'whatsapp', 'both'], true)) {
            throw new \InvalidArgumentException('Channel must be email, whatsapp, or both.');
        }

        $employee->loadMissing('user');
        $user = $employee->user;
        $temporaryPassword = null;
        $portalCreated = false;

        if (! $user) {
            $portal = $this->provisionPortalUser($employee, $portalEmail, notify: false);

            if ($portal['skipped'] ?? false) {
                throw new \RuntimeException($portal['reason'] ?? 'Could not create portal access.');
            }

            $user = $portal['user'];
            $temporaryPassword = $portal['temporary_password'];
            $portalCreated = true;
            $employee->refresh();
        } else {
            $temporaryPassword = $this->generateTemporaryPassword();
            $user->update([
                'password' => $temporaryPassword,
                'initial_login_password' => $temporaryPassword,
            ]);
        }

        if (($channel === 'whatsapp' || $channel === 'both') && ! $this->normalizePhone($employee->phone ?? $user->phone)) {
            throw new \RuntimeException('Employee phone number is required to send login details on WhatsApp.');
        }

        $sentEmail = false;
        $sentWhatsapp = false;

        if ($channel === 'email' || $channel === 'both') {
            $sentEmail = $this->notifyPortalCredentials($employee, $user, (string) $temporaryPassword);
        }

        if ($channel === 'whatsapp' || $channel === 'both') {
            $sentWhatsapp = $this->notifyPortalCredentialsWhatsapp($employee, $user, (string) $temporaryPassword);
        }

        if (($channel === 'email' || $channel === 'both') && ! $sentEmail) {
            throw new \RuntimeException('Failed to send login details by email. Check SMTP in Admin → Settings → Integrations.');
        }

        if (($channel === 'whatsapp' || $channel === 'both') && ! $sentWhatsapp) {
            throw new \RuntimeException('Failed to send login details on WhatsApp. Check WhatsApp integration and employee phone number.');
        }

        return [
            'email' => $user->email,
            'temporary_password' => $temporaryPassword,
            'sent_email' => $sentEmail,
            'sent_whatsapp' => $sentWhatsapp,
            'portal_created' => $portalCreated,
        ];
    }

    /**
     * @return array{
     *     email: string,
     *     temporary_password: string,
     *     credentials_emailed: bool
     * }
     */
    public function resendPortalCredentials(Employee $employee): array
    {
        $result = $this->sendPortalLoginDetails($employee, 'email');

        return [
            'email' => $result['email'],
            'temporary_password' => (string) $result['temporary_password'],
            'credentials_emailed' => $result['sent_email'],
        ];
    }

    private function notifyPortalCredentials(Employee $employee, User $user, string $temporaryPassword): bool
    {
        [$message, $details] = $this->buildCredentialsPayload($employee, $user, $temporaryPassword);

        return $this->sendCredentialsEmail(
            $user,
            'Employee portal login details',
            $message,
            $details,
        );
    }

    private function notifyPortalCredentialsWhatsapp(Employee $employee, User $user, string $temporaryPassword): bool
    {
        [$message] = $this->buildCredentialsPayload($employee, $user, $temporaryPassword);

        return $this->sendCredentialsWhatsapp($employee, $message);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildCredentialsPayload(Employee $employee, User $user, string $temporaryPassword): array
    {
        $company = $this->profile->displayName();
        $loginUrl = $this->employeePortalUrl();

        $message = "Dear {$employee->full_name},\n\n"
            ."Congratulations! You have been onboarded at {$company}.\n\n"
            ."Employee ID: {$employee->employee_code}\n"
            .($employee->designation ? "Designation: {$employee->designation}\n" : '')
            .($employee->department ? "Department: {$employee->department}\n" : '')
            ."\nYour employee portal login details:\n"
            ."Portal URL: {$loginUrl}\n"
            ."Email: {$user->email}\n"
            ."Temporary password: {$temporaryPassword}\n\n"
            ."Please sign in and change your password after your first login.\n\n"
            ."Regards,\n{$company} HR Team";

        return [
            $message,
            [
                'Portal URL' => $loginUrl,
                'Email' => $user->email,
                'Temporary password' => $temporaryPassword,
            ],
        ];
    }

    private function notifyExistingPortalAccess(Employee $employee, User $user): bool
    {
        $company = $this->profile->displayName();
        $loginUrl = $this->employeePortalUrl();

        $message = "Dear {$employee->full_name},\n\n"
            ."Your employee profile at {$company} is now active.\n\n"
            ."Employee ID: {$employee->employee_code}\n"
            .($employee->designation ? "Designation: {$employee->designation}\n" : '')
            .($employee->department ? "Department: {$employee->department}\n" : '')
            ."\nSign in to the employee portal using your existing account.\n\n"
            ."Use your existing password. Contact HR if you need help accessing your account.\n\n"
            ."Regards,\n{$company} HR Team";

        return $this->sendCredentialsEmail(
            $user,
            'Employee portal access',
            $message,
            [
                'Portal URL' => $loginUrl,
                'Email' => $user->email,
            ],
        );
    }

    /**
     * @param  array<string, string>  $details
     */
    private function sendCredentialsEmail(User $user, string $title, string $message, array $details = []): bool
    {
        try {
            $this->smtpMail->send(
                $user->email,
                $this->templates->formatSubject($title),
                $message,
                $title,
                $details,
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Employee portal credentials email failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendCredentialsWhatsapp(Employee $employee, string $message): bool
    {
        $whatsapp = $this->integrations->whatsapp();
        $phone = $this->normalizePhone($employee->phone);

        if (! $whatsapp || ! $phone) {
            return false;
        }

        try {
            $response = Http::withToken($whatsapp['access_token'])
                ->post("https://graph.facebook.com/{$whatsapp['api_version']}/{$whatsapp['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if (! $response->successful()) {
                Log::warning('Employee portal credentials WhatsApp failed', [
                    'employee_id' => $employee->id,
                    'phone' => $phone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Employee portal credentials WhatsApp error', [
                'employee_id' => $employee->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function generateTemporaryPassword(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false);
    }

    private function assertDeliverableEmail(string $email): void
    {
        $domain = strtolower(ltrim(strrchr($email, '@') ?: '', '@'));

        if ($domain === '') {
            throw new \RuntimeException('Enter a valid portal login email address.');
        }

        $blocked = ['test', 'invalid', 'localhost', 'example', 'local'];
        $blockedSuffixes = ['.test', '.invalid', '.local', '.localhost'];

        if (in_array($domain, $blocked, true)) {
            throw new \RuntimeException("Email domain .{$domain} cannot receive mail. Use a real email address (e.g. Gmail, company email).");
        }

        foreach ($blockedSuffixes as $suffix) {
            if (str_ends_with($domain, $suffix)) {
                throw new \RuntimeException("Email domain {$domain} cannot receive mail. Use a real email address (e.g. Gmail, company email).");
            }
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }
}
