<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Enums\UserRole;
use App\Models\CompanyRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves emails for joining / resignation process roles.
 */
class HrProcessEmailResolver
{
    public function __construct(
        private readonly InvoiceProfileService $profile,
    ) {}

    /**
     * @param  array<int, string>  $roles  company|employee|hr|recruiter|founder|it_admin|accounts|reporting_manager
     * @return array<int, string>
     */
    public function emailsFor(Employee $employee, array $roles): array
    {
        $emails = collect();

        foreach (array_unique($roles) as $role) {
            $emails = $emails->merge($this->resolveRole($employee, $role));
        }

        return $emails
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveRole(Employee $employee, string $role): Collection
    {
        return match ($role) {
            'company' => collect([$this->companyEmail()]),
            'employee' => $this->employeeEmails($employee),
            'hr' => $this->hrEmails(),
            'recruiter' => $this->emailsForCompanyRoleSlugs(['recruiter']),
            'founder' => $this->emailsForCompanyRoleSlugs([
                'founder-owner',
                'director',
                'ceo-chief-executive-officer',
            ]),
            'it_admin' => $this->emailsForCompanyRoleSlugs([
                'it-admin',
                'admin-executive',
                'devops-engineer',
                'office-administrator',
            ]),
            'accounts' => $this->emailsForCompanyRoleSlugs([
                'accountant',
                'billing-executive',
            ]),
            'reporting_manager' => $this->reportingManagerEmails($employee),
            default => collect(),
        };
    }

    private function companyEmail(): ?string
    {
        $email = trim((string) ($this->profile->company()['email'] ?? ''));

        return $email !== '' ? $email : null;
    }

    /**
     * @return Collection<int, string>
     */
    private function employeeEmails(Employee $employee): Collection
    {
        $employee->loadMissing('user');

        return collect([
            $employee->user?->email,
            $employee->email,
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function hrEmails(): Collection
    {
        $portalHr = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::HrManager, UserRole::SuperAdmin])
            ->pluck('email');

        $roleHr = $this->emailsForCompanyRoleSlugs(['hr-executive', 'recruiter']);

        return $portalHr->merge($roleHr);
    }

    /**
     * @param  array<int, string>  $slugs
     * @return Collection<int, string>
     */
    private function emailsForCompanyRoleSlugs(array $slugs): Collection
    {
        $roleIds = CompanyRole::query()
            ->whereIn('slug', $slugs)
            ->where('is_active', true)
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->with('user:id,email')
            ->whereIn('company_role_id', $roleIds)
            ->whereIn('status', [EmployeeStatus::Active->value, EmployeeStatus::Probation->value])
            ->get()
            ->flatMap(fn (Employee $member) => [$member->user?->email, $member->email]);
    }

    /**
     * @return Collection<int, string>
     */
    private function reportingManagerEmails(Employee $employee): Collection
    {
        $name = trim((string) ($employee->reporting_manager ?? ''));
        if ($name === '') {
            return collect();
        }

        $normalized = mb_strtolower($name);

        $byEmployee = Employee::query()
            ->with('user:id,email')
            ->whereIn('status', [EmployeeStatus::Active->value, EmployeeStatus::Probation->value])
            ->whereRaw('LOWER(full_name) = ?', [$normalized])
            ->get()
            ->flatMap(fn (Employee $manager) => [$manager->user?->email, $manager->email]);

        if ($byEmployee->filter()->isNotEmpty()) {
            return $byEmployee;
        }

        return User::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->pluck('email');
    }
}
