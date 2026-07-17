<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EmployeeDocumentCategory;
use App\Enums\EmployeeDocumentStage;
use App\Enums\EmployeeExitStatus;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeExitRecord;
use App\Services\EmployeeAccountService;
use App\Services\EmployeeService;
use App\Services\HrStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['exitRecord', 'companyRole'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('employee_code', 'like', $term);
            });
        }

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreEmployeeRequest $request, EmployeeService $service): JsonResponse
    {
        $result = $service->createDirect($request->validated());
        $portal = $result['portal'];

        if ($portal['skipped'] ?? false) {
            return $this->success([
                'employee' => $result['employee'],
                'portal_login' => [
                    'skipped' => true,
                    'reason' => $portal['reason'] ?? 'Could not create portal access.',
                    'email' => $portal['login_email'] ?? null,
                ],
            ], 'Employee profile created without portal login.', 201);
        }

        return $this->success([
            'employee' => $result['employee'],
            'portal_login' => [
                'email' => $portal['user']?->email,
                'temporary_password' => $portal['temporary_password'],
                'login_url' => '/employee',
                'credentials_emailed' => $portal['credentials_emailed'] ?? false,
            ],
        ], 'Employee created.', 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return $this->success($employee->load(['documents', 'exitRecord', 'jobApplication.career', 'companyRole']));
    }

    public function update(Request $request, Employee $employee, EmployeeService $service): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:255'],
            'company_role_id' => ['nullable', 'integer', 'exists:company_roles,id'],
            'designation' => ['nullable', 'string', 'max:255'],
            'date_of_joining' => ['nullable', 'date'],
            'reporting_manager' => ['nullable', 'string', 'max:255'],
            'salary_details' => ['nullable', 'array'],
            'pf_uan' => ['nullable', 'string', 'max:50'],
            'esic_number' => ['nullable', 'string', 'max:50'],
            'bank_details' => ['nullable', 'array'],
            'emergency_contact' => ['nullable', 'array'],
            'status' => ['nullable', 'string', Rule::in(EmployeeStatus::values())],
        ]);

        $employee = $service->update($employee, $data);

        return $this->success($employee, 'Employee updated.');
    }

    public function destroy(Employee $employee, EmployeeService $service, Request $request): JsonResponse
    {
        if ($request->user()?->role === \App\Enums\UserRole::HrManager) {
            return $this->error('Only super admin can delete employees.', 403);
        }

        $service->delete($employee);

        return $this->success(null, 'Employee deleted.');
    }

    public function uploadDocument(Request $request, Employee $employee, EmployeeService $service): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(EmployeeDocumentCategory::hrManagedValues())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $document = $service->uploadDocument(
            $employee,
            $request->file('file'),
            $data['category'],
            $data['notes'] ?? null,
        );

        return $this->success($document, 'Document uploaded.', 201);
    }

    public function downloadDocument(Employee $employee, EmployeeDocument $document, HrStorageService $storage): JsonResponse
    {
        abort_unless((int) $document->employee_id === (int) $employee->id, 404);

        $token = $storage->createSignedDownloadToken($document->file_path, $document->original_name);

        return $this->success([
            'download_url' => url("/api/v1/hr/documents/download?token={$token}"),
            'original_name' => $document->original_name,
        ]);
    }

    public function initiateExit(Request $request, Employee $employee, EmployeeService $service): JsonResponse
    {
        $data = $request->validate([
            'resignation_date' => ['nullable', 'date'],
            'last_working_day' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'hr_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $exit = $service->initiateExit($employee, $data);

        return $this->success($exit->load('employee'), 'Exit process initiated.', 201);
    }

    public function updateExit(Request $request, Employee $employee, EmployeeService $service): JsonResponse
    {
        $exit = $employee->exitRecord ?? abort(404, 'Exit record not found.');

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(EmployeeExitStatus::values())],
            'last_working_day' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'hr_remarks' => ['nullable', 'string', 'max:2000'],
            'checklist' => ['nullable', 'array'],
        ]);

        $updated = $service->updateExit($exit, $data);

        return $this->success($updated, 'Exit record updated.');
    }

    public function uploadExitDocument(Request $request, Employee $employee, EmployeeService $service): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(EmployeeDocumentCategory::valuesForStage(EmployeeDocumentStage::Exit))],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $document = $service->uploadExitDocument($employee, $request->file('file'), $data['category']);

        return $this->success($document, 'Exit document uploaded.', 201);
    }

    public function provisionPortal(Request $request, Employee $employee, EmployeeAccountService $accounts): JsonResponse
    {
        abort_if($employee->user_id, 422, 'This employee already has portal access.');

        $data = $request->validate([
            'portal_email' => ['nullable', 'email', 'max:255'],
        ]);

        $portal = $accounts->provisionPortalUser(
            $employee,
            filled($data['portal_email'] ?? null) ? (string) $data['portal_email'] : null,
        );

        if ($portal['skipped'] ?? false) {
            return $this->error($portal['reason'] ?? 'Could not create portal access.', 422);
        }

        return $this->success([
            'portal_login' => [
                'email' => $portal['user']?->email,
                'temporary_password' => $portal['temporary_password'],
                'login_url' => '/employee',
                'credentials_emailed' => $portal['credentials_emailed'] ?? false,
            ],
        ], 'Employee portal access created.');
    }

    public function resendPortalLogin(Employee $employee, EmployeeAccountService $accounts): JsonResponse
    {
        try {
            $result = $accounts->sendPortalLoginDetails($employee, 'email');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'portal_login' => [
                'email' => $result['email'],
                'temporary_password' => $result['temporary_password'],
                'login_url' => '/employee',
                'credentials_emailed' => $result['sent_email'],
            ],
        ], $result['sent_email']
            ? 'Login details emailed successfully.'
            : 'Could not send login email. Share the temporary password manually.');
    }

    public function sendPortalLogin(Request $request, Employee $employee, EmployeeAccountService $accounts): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', 'in:email,whatsapp,both'],
            'portal_email' => ['nullable', 'email', 'max:255'],
        ]);

        try {
            $result = $accounts->sendPortalLoginDetails(
                $employee,
                $data['channel'],
                filled($data['portal_email'] ?? null) ? (string) $data['portal_email'] : null,
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $channel = $data['channel'];
        $message = match ($channel) {
            'email' => 'Login details sent by email.',
            'whatsapp' => 'Login details sent on WhatsApp.',
            default => 'Login details sent by email and WhatsApp.',
        };

        return $this->success([
            'portal_login' => [
                'email' => $result['email'],
                'temporary_password' => $result['temporary_password'],
                'login_url' => '/employee',
                'sent_email' => $result['sent_email'],
                'sent_whatsapp' => $result['sent_whatsapp'],
                'portal_created' => $result['portal_created'],
            ],
        ], $message);
    }
}
