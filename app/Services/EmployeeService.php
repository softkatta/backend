<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\EmployeeDocumentCategory;
use App\Enums\EmployeeExitStatus;
use App\Enums\EmployeeStatus;
use App\Models\CompanyRole;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeExitRecord;
use App\Models\JobApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        private readonly HrStorageService $storage,
        private readonly EmployeeAccountService $accounts,
        private readonly EmployeeDocumentMailService $documentMail,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{employee: Employee, portal: array<string, mixed>}
     */
    public function createDirect(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $companyRole = $this->resolveCompanyRole($data);

            $employee = Employee::create([
                'employee_code' => $data['employee_code'] ?? $this->generateEmployeeCode(),
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'department' => $data['department'] ?? null,
                'company_role_id' => $companyRole?->id,
                'designation' => $data['designation'] ?? $companyRole?->name,
                'date_of_joining' => $data['date_of_joining'] ?? now()->toDateString(),
                'reporting_manager' => $data['reporting_manager'] ?? null,
                'status' => EmployeeStatus::Probation->value,
            ]);

            $portal = $this->accounts->provisionPortalUser(
                $employee,
                filled($data['portal_email'] ?? null) ? (string) $data['portal_email'] : null,
            );

            return [
                'employee' => $employee->fresh(['documents', 'exitRecord', 'user', 'companyRole']),
                'portal' => $portal,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{employee: Employee, portal: array{user: \App\Models\User, temporary_password: string|null}}
     */
    public function createFromApplication(JobApplication $application, array $data): array
    {
        return DB::transaction(function () use ($application, $data) {
            if (! filled($data['company_role_id'] ?? null)) {
                $application->loadMissing('career.companyRole');
                if ($application->career?->company_role_id) {
                    $data['company_role_id'] = $application->career->company_role_id;
                }
            }

            $companyRole = $this->resolveCompanyRole($data);

            $employee = Employee::create([
                'job_application_id' => $application->id,
                'employee_code' => $data['employee_code'] ?? $this->generateEmployeeCode(),
                'full_name' => $data['full_name'] ?? $application->name,
                'email' => $data['email'] ?? $application->email,
                'phone' => $data['phone'] ?? $application->phone,
                'department' => $data['department'] ?? $application->career?->department,
                'company_role_id' => $companyRole?->id,
                'designation' => $data['designation'] ?? $companyRole?->name ?? $application->career?->title,
                'date_of_joining' => $data['date_of_joining'] ?? now()->toDateString(),
                'reporting_manager' => $data['reporting_manager'] ?? null,
                'salary_details' => $data['salary_details'] ?? null,
                'pf_uan' => $data['pf_uan'] ?? null,
                'esic_number' => $data['esic_number'] ?? null,
                'bank_details' => $data['bank_details'] ?? null,
                'emergency_contact' => $data['emergency_contact'] ?? null,
                'status' => EmployeeStatus::Probation->value,
            ]);

            $application->update([
                'employee_id' => $employee->id,
                'status' => ApplicationStatus::Joined->value,
            ]);

            $portal = $this->accounts->provisionPortalUser(
                $employee,
                filled($data['portal_email'] ?? null) ? (string) $data['portal_email'] : null,
            );

            return [
                'employee' => $employee->fresh(['documents', 'exitRecord', 'user']),
                'portal' => $portal,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        if (array_key_exists('company_role_id', $data)) {
            $companyRole = $this->resolveCompanyRole($data);
            $data['company_role_id'] = $companyRole?->id;

            if ($companyRole && empty($data['designation'])) {
                $data['designation'] = $companyRole->name;
            }
        }

        $employee->update($data);

        return $employee->fresh(['documents', 'exitRecord', 'jobApplication', 'companyRole']);
    }

    public function uploadDocument(Employee $employee, UploadedFile $file, string $category, ?string $notes = null): EmployeeDocument
    {
        $stored = $this->storage->storeEmployeeDocument($file, $employee->id, $category);

        $document = EmployeeDocument::create([
            'employee_id' => $employee->id,
            'notes' => $notes,
            ...$stored,
        ]);

        $this->documentMail->notifyUploaded($employee->fresh(['user']), $document);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function initiateExit(Employee $employee, array $data): EmployeeExitRecord
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->update(['status' => EmployeeStatus::OnNotice->value]);

            return EmployeeExitRecord::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'status' => EmployeeExitStatus::Initiated->value,
                    'resignation_date' => $data['resignation_date'] ?? now()->toDateString(),
                    'last_working_day' => $data['last_working_day'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'hr_remarks' => $data['hr_remarks'] ?? null,
                    'checklist' => $data['checklist'] ?? $this->defaultExitChecklist(),
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateExit(EmployeeExitRecord $exit, array $data): EmployeeExitRecord
    {
        $exit->update($data);

        if (($data['status'] ?? null) === EmployeeExitStatus::Completed->value) {
            $exit->employee?->update(['status' => EmployeeStatus::Exited->value]);
        }

        return $exit->fresh('employee');
    }

    public function uploadExitDocument(Employee $employee, UploadedFile $file, string $category): EmployeeDocument
    {
        return $this->uploadDocument($employee, $file, $category);
    }

    public function delete(Employee $employee, bool $revokePortalAccess = true): void
    {
        DB::transaction(function () use ($employee, $revokePortalAccess): void {
            $employee->load(['documents', 'jobApplication']);

            foreach ($employee->documents as $document) {
                $this->storage->delete($document->file_path);
            }

            $this->storage->deleteEmployeeDirectory($employee->id);

            if ($employee->jobApplication) {
                $employee->jobApplication->update([
                    'employee_id' => null,
                    'status' => ApplicationStatus::Selected->value,
                ]);
            }

            $userId = $employee->user_id;
            $employee->delete();

            if ($revokePortalAccess && $userId) {
                $this->accounts->revokePortalAccess($userId);
            }
        });
    }

    private function generateEmployeeCode(): string
    {
        $next = Employee::query()->count() + 1;

        return 'SK-EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCompanyRole(array $data): ?CompanyRole
    {
        if (! filled($data['company_role_id'] ?? null)) {
            return null;
        }

        return CompanyRole::query()
            ->whereKey($data['company_role_id'])
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, bool>
     */
    private function defaultExitChecklist(): array
    {
        return [
            EmployeeDocumentCategory::ResignationForm->value => false,
            EmployeeDocumentCategory::ResignationAcceptance->value => false,
            EmployeeDocumentCategory::ExitInterview->value => false,
            EmployeeDocumentCategory::NoDues->value => false,
            EmployeeDocumentCategory::AssetHandover->value => false,
            EmployeeDocumentCategory::FullAndFinal->value => false,
            EmployeeDocumentCategory::ExperienceLetter->value => false,
            EmployeeDocumentCategory::RelievingLetter->value => false,
            EmployeeDocumentCategory::Form16->value => false,
            EmployeeDocumentCategory::PfGratuity->value => false,
        ];
    }
}
