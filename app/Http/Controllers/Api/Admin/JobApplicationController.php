<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\ApplicationDocument;
use App\Models\JobApplication;
use App\Services\CareerApplicationService;
use App\Services\EmployeeService;
use App\Services\HrStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobApplicationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = JobApplication::with(['career:id,title,slug,department,company_role_id', 'career.companyRole:id,name', 'documents'])
            ->latest();

        if ($request->filled('career_id')) {
            $query->where('career_id', $request->integer('career_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('qualification', 'like', $term);
            });
        }

        $sort = $request->string('sort', 'created_at');
        $direction = $request->string('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['created_at', 'name', 'status', 'expected_salary'], true)) {
            $query->orderBy($sort, $direction);
        }

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }

    public function show(JobApplication $jobApplication): JsonResponse
    {
        return $this->success($jobApplication->load(['career:id,title,slug,department,company_role_id', 'career.companyRole:id,name', 'documents', 'employee']));
    }

    public function update(Request $request, JobApplication $jobApplication, CareerApplicationService $service): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(ApplicationStatus::values())],
            'hr_remarks' => ['nullable', 'string', 'max:5000'],
            'interview_scheduled_at' => ['nullable', 'date'],
        ]);

        $application = $service->updateStatus(
            $jobApplication,
            $data['status'],
            $data['hr_remarks'] ?? null,
            $data['interview_scheduled_at'] ?? null,
        );

        return $this->success($application, 'Application updated.');
    }

    public function convertToEmployee(Request $request, JobApplication $jobApplication, EmployeeService $employeeService): JsonResponse
    {
        $data = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:255'],
            'company_role_id' => ['nullable', 'integer', 'exists:company_roles,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'date_of_joining' => ['nullable', 'date'],
            'reporting_manager' => ['nullable', 'string', 'max:255'],
            'salary_details' => ['nullable', 'array'],
            'pf_uan' => ['nullable', 'string', 'max:50'],
            'esic_number' => ['nullable', 'string', 'max:50'],
            'bank_details' => ['nullable', 'array'],
            'emergency_contact' => ['nullable', 'array'],
            'portal_email' => ['nullable', 'email', 'max:255'],
        ]);

        abort_if($jobApplication->employee_id, 422, 'Employee profile already exists for this application.');

        $result = $employeeService->createFromApplication($jobApplication, $data);

        $portal = $result['portal'];

        return $this->success([
            'employee' => $result['employee'],
            'portal_login' => ($portal['skipped'] ?? false)
                ? [
                    'skipped' => true,
                    'reason' => $portal['reason'] ?? 'Portal login was not created.',
                    'login_email' => $portal['login_email'] ?? null,
                ]
                : [
                    'skipped' => false,
                    'email' => $portal['user']?->email,
                    'temporary_password' => $portal['temporary_password'],
                    'login_url' => '/employee',
                    'credentials_emailed' => $portal['credentials_emailed'] ?? false,
                ],
        ], ($portal['skipped'] ?? false)
            ? 'Employee profile created. Portal login was not created — see details.'
            : 'Employee profile created.', 201);
    }

    public function downloadDocument(JobApplication $jobApplication, ApplicationDocument $document, HrStorageService $storage): JsonResponse
    {
        abort_unless((int) $document->job_application_id === (int) $jobApplication->id, 404);

        $token = $storage->createSignedDownloadToken($document->file_path, $document->original_name);

        return $this->success([
            'download_url' => url("/api/v1/hr/documents/download?token={$token}"),
            'original_name' => $document->original_name,
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $format = $request->string('format', 'csv');
        $applications = JobApplication::with('career:id,title,department')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->get();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.job-applications', ['applications' => $applications]);

            return $pdf->download('job-applications-'.now()->format('Y-m-d').'.pdf');
        }

        $filename = 'job-applications-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($applications): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Name', 'Email', 'Phone', 'Job Title', 'Department', 'Status',
                'Qualification', 'Experience', 'Expected Salary', 'Applied At',
            ]);
            foreach ($applications as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->name,
                    $row->email,
                    $row->phone,
                    $row->career?->title,
                    $row->career?->department,
                    $row->status,
                    $row->qualification,
                    $row->total_experience,
                    $row->expected_salary,
                    $row->created_at?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(JobApplication $jobApplication, HrStorageService $storage): JsonResponse
    {
        foreach ($jobApplication->documents as $document) {
            $storage->delete($document->file_path);
        }
        if ($jobApplication->resume_path) {
            $storage->delete($jobApplication->resume_path);
        }

        $this->permanentlyDelete($jobApplication);

        return $this->success(null, 'Application deleted.');
    }
}
