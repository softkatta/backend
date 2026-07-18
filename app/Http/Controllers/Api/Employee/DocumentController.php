<?php

namespace App\Http\Controllers\Api\Employee;

use App\Enums\EmployeeDocumentCategory;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\EmployeeDocument;
use App\Services\EmployeeIdCardService;
use App\Services\EmployeePortalService;
use App\Services\HrStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DocumentController extends BaseApiController
{
    use ResolvesEmployeeProfile;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);
        $documents = $employee->documents()->latest()->get();

        $selfService = EmployeeDocumentCategory::selfServiceValues();

        return $this->success([
            'company_documents' => $documents->whereNotIn('category', $selfService)->values(),
            'my_submissions' => $documents->whereIn('category', $selfService)->values(),
        ]);
    }

    public function store(Request $request, EmployeePortalService $portal): JsonResponse
    {
        $employee = $this->employeeFor($request);

        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(EmployeeDocumentCategory::selfServiceValues())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $document = $portal->uploadSelfServiceDocument(
            $employee,
            $request->file('file'),
            $data['category'],
            $data['notes'] ?? null,
        );

        return $this->success($document, 'Document uploaded.', 201);
    }

    public function download(Request $request, EmployeeDocument $document, HrStorageService $storage): JsonResponse
    {
        $employee = $this->employeeFor($request);
        abort_unless((int) $document->employee_id === (int) $employee->id, 404);

        $token = $storage->createSignedDownloadToken($document->file_path, $document->original_name);

        return $this->success([
            'download_url' => url("/api/v1/hr/documents/download?token={$token}"),
            'original_name' => $document->original_name,
        ]);
    }

    public function downloadIdCard(Request $request, EmployeeIdCardService $idCards): Response
    {
        $employee = $this->employeeFor($request);
        $pdf = $idCards->generateFor($employee);
        $code = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($employee->employee_code ?: $employee->id));

        return $pdf->download("id-card-{$code}.pdf");
    }
}
