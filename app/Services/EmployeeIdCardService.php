<?php

namespace App\Services;

use App\Enums\ApplicationDocumentType;
use App\Enums\EmployeeDocumentCategory;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class EmployeeIdCardService
{
    public function __construct(
        private readonly InvoiceProfileService $profile,
    ) {}

    /**
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateFor(Employee $employee)
    {
        $cards = collect([$this->cardPayload($employee)]);

        return $this->renderPdf($cards, single: true);
    }

    /**
     * Active / probation / on-notice employees (not exited).
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateForAll(?string $status = null)
    {
        $query = Employee::query()
            ->with(['companyRole', 'documents', 'jobApplication.documents'])
            ->orderBy('employee_code');

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [
                EmployeeStatus::Active->value,
                EmployeeStatus::Probation->value,
                EmployeeStatus::OnNotice->value,
            ]);
        }

        $employees = $query->get();
        $cards = $employees->map(fn (Employee $employee) => $this->cardPayload($employee));

        return $this->renderPdf($cards, single: false);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return \Barryvdh\DomPDF\PDF
     */
    private function renderPdf(Collection $cards, bool $single)
    {
        $company = $this->profile->company();

        $pdf = Pdf::loadView('hr.id-cards', [
            'cards' => $cards,
            'company' => $company,
            'logo_file' => $this->profile->logoAbsolutePath($company['logo'] ?? null),
            'colors' => config('invoice.colors'),
            'single' => $single,
        ]);

        return $pdf->setPaper('a4', 'portrait');
    }

    /**
     * @return array<string, mixed>
     */
    public function cardPayload(Employee $employee): array
    {
        $employee->loadMissing(['companyRole', 'documents', 'jobApplication.documents']);

        $designation = $employee->designation
            ?: ($employee->companyRole?->name ?? 'Team member');
        $department = $employee->department
            ?: ($employee->companyRole?->category ?? 'SoftKatta');

        return [
            'full_name' => (string) $employee->full_name,
            'employee_code' => (string) ($employee->employee_code ?? '—'),
            'designation' => (string) $designation,
            'department' => (string) $department,
            'date_of_joining' => $employee->date_of_joining?->format('d M Y') ?? '—',
            'email' => (string) ($employee->email ?? ''),
            'photo_uri' => $this->resolvePhotoDataUri($employee),
            'initials' => $this->initials((string) $employee->full_name),
            'qr_uri' => $this->qrDataUri((string) ($employee->employee_code ?? $employee->id)),
        ];
    }

    private function resolvePhotoDataUri(Employee $employee): ?string
    {
        $photoDoc = $employee->documents
            ->where('category', EmployeeDocumentCategory::Photo->value)
            ->sortByDesc('id')
            ->first();

        if ($photoDoc) {
            $uri = $this->fileToDataUri($photoDoc->file_path, $photoDoc->mime_type);
            if ($uri) {
                return $uri;
            }
        }

        $applicationPhoto = $employee->jobApplication?->documents
            ?->where('document_type', ApplicationDocumentType::Photo->value)
            ->sortByDesc('id')
            ->first();

        if ($applicationPhoto) {
            return $this->fileToDataUri($applicationPhoto->file_path, $applicationPhoto->mime_type);
        }

        return null;
    }

    private function fileToDataUri(?string $path, ?string $mime): ?string
    {
        if (! $path) {
            return null;
        }

        $absolute = is_file($path) ? $path : null;
        if (! $absolute && Storage::disk('local')->exists($path)) {
            $absolute = Storage::disk('local')->path($path);
        }

        if (! $absolute || ! is_file($absolute)) {
            return null;
        }

        $mimeType = $mime ?: (mime_content_type($absolute) ?: 'image/jpeg');
        if (! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $contents = @file_get_contents($absolute);
        if ($contents === false) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    private function qrDataUri(string $payload, int $size = 90): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd
            )
        );

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($payload));
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'SK';
    }
}
