<?php

namespace App\Services;

use App\Enums\EmployeeDocumentCategory;
use App\Enums\EmployeeDocumentProvider;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentMailService
{
    public function __construct(
        private readonly SmtpMailService $smtpMail,
        private readonly InvoiceProfileService $profile,
        private readonly HrProcessEmailResolver $roleEmails,
    ) {}

    /**
     * Email an uploaded HR document to process-related roles + company (with attachment).
     */
    public function notifyUploaded(Employee $employee, EmployeeDocument $document): void
    {
        $category = EmployeeDocumentCategory::tryFrom((string) $document->category);
        if (! $category) {
            return;
        }

        $roles = $category->emailRecipients();
        if ($roles === []) {
            return;
        }

        $recipients = $this->roleEmails->emailsFor($employee, $roles);
        if ($recipients === []) {
            Log::info('HR document email skipped — no recipients', [
                'employee_id' => $employee->id,
                'document_id' => $document->id,
                'category' => $category->value,
                'roles' => $roles,
            ]);

            return;
        }

        $absolutePath = $this->absolutePath($document->file_path);
        if (! $absolutePath) {
            Log::warning('HR document email skipped — file missing', [
                'document_id' => $document->id,
                'path' => $document->file_path,
            ]);

            return;
        }

        $companyName = (string) ($this->profile->company()['name'] ?? 'SoftKatta');
        $label = $category->label();
        $employeeName = $employee->full_name ?: 'Employee';
        $provider = $category->providedBy();
        $providerLabel = $provider === EmployeeDocumentProvider::Company
            ? 'Company provides'
            : 'Employee submits';

        $title = "{$label} — {$employeeName}";

        $body = $provider === EmployeeDocumentProvider::Company
            ? "{$companyName} has issued \"{$label}\" for {$employeeName} ({$employee->employee_code}).\n\nPlease find the attachment."
            : "Document \"{$label}\" for {$employeeName} ({$employee->employee_code}) has been uploaded.\n\nPlease find the attachment.";

        $details = [
            'Employee' => $employeeName,
            'Employee code' => (string) ($employee->employee_code ?? '—'),
            'Document' => $label,
            'Provided by' => $providerLabel,
            'File' => (string) $document->original_name,
        ];

        $attachments = [[
            'path' => $absolutePath,
            'name' => $document->original_name ?: basename($absolutePath),
            'mime' => $document->mime_type ?: null,
        ]];

        foreach ($recipients as $email) {
            try {
                $this->smtpMail->send(
                    $email,
                    $title,
                    $body,
                    $title,
                    $details,
                    $attachments,
                );
            } catch (\Throwable $e) {
                Log::warning('HR document email failed', [
                    'to' => $email,
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function absolutePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }
}
