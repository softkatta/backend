<?php

namespace App\Services;

use App\Enums\ApplicationDocumentType;
use App\Enums\ApplicationStatus;
use App\Enums\NotificationChannel;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\Career;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CareerApplicationService
{
    public function __construct(
        private readonly HrStorageService $storage,
        private readonly NotificationService $notifications,
        private readonly InvoiceProfileService $profile,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(Career $career, array $data, Request $request): JobApplication
    {
        return DB::transaction(function () use ($career, $data, $request) {
            $application = JobApplication::create([
                'career_id' => $career->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'current_address' => $data['current_address'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'skills' => $data['skills'] ?? null,
                'total_experience' => $data['total_experience'] ?? null,
                'current_company' => $data['current_company'] ?? null,
                'current_salary' => $data['current_salary'] ?? null,
                'expected_salary' => $data['expected_salary'] ?? null,
                'notice_period' => $data['notice_period'] ?? null,
                'preferred_location' => $data['preferred_location'] ?? null,
                'message' => $data['message'] ?? '',
                'status' => ApplicationStatus::Applied->value,
            ]);

            foreach (ApplicationDocumentType::cases() as $type) {
                $field = $type->value;
                if ($request->hasFile($field)) {
                    $stored = $this->storage->storeApplicationDocument(
                        $request->file($field),
                        $application->id,
                        $field,
                    );
                    ApplicationDocument::create([
                        'job_application_id' => $application->id,
                        ...$stored,
                    ]);

                    if ($type === ApplicationDocumentType::Resume) {
                        $application->update(['resume_path' => $stored['file_path']]);
                    }
                }
            }

            $this->notifyCandidate($application, $career);
            $this->notifyHrTeam($application, $career);

            return $application->fresh(['documents', 'career']);
        });
    }

    public function updateStatus(JobApplication $application, string $status, ?string $hrRemarks = null, ?string $interviewAt = null): JobApplication
    {
        $previous = $application->status;

        $application->update([
            'status' => $status,
            'hr_remarks' => $hrRemarks ?? $application->hr_remarks,
            'interview_scheduled_at' => $interviewAt ?: $application->interview_scheduled_at,
        ]);

        if ($previous !== $status) {
            $this->notifyStatusChange($application->fresh('career'), $status);
        }

        return $application->fresh(['career', 'documents']);
    }

    private function notifyCandidate(JobApplication $application, Career $career): void
    {
        $company = $this->profile->displayName();
        $message = "Dear {$application->name},\n\nThank you for applying for {$career->title} at {$company}. "
            ."We have received your application and our HR team will review it shortly.\n\nRegards,\n{$company} HR Team";

        $this->sendGuestEmail($application->email, "Application received — {$career->title}", $message);
    }

    private function notifyHrTeam(JobApplication $application, Career $career): void
    {
        $admins = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::SuperAdmin, UserRole::HrManager])
            ->get();

        $title = 'New job application received';
        $message = "{$application->name} applied for {$career->title}. Email: {$application->email}.";

        foreach ($admins as $admin) {
            $this->notifications->send(
                $admin,
                'hr_application',
                $title,
                $message,
                NotificationService::allChannels(),
                ['application_id' => $application->id, 'career_id' => $career->id],
            );
        }
    }

    private function notifyStatusChange(JobApplication $application, string $status): void
    {
        $careerTitle = $application->career?->title ?? 'your application';
        $label = str_replace('_', ' ', ucfirst($status));
        $company = $this->profile->displayName();

        $message = "Dear {$application->name},\n\nYour application for {$careerTitle} has been updated to: {$label}."
            .($application->hr_remarks ? "\n\nHR note: {$application->hr_remarks}" : '')
            ."\n\nRegards,\n{$company} HR Team";

        $this->sendGuestEmail($application->email, "Application update — {$careerTitle}", $message);
    }

    private function sendGuestEmail(string $email, string $title, string $message): void
    {
        try {
            $guest = new User([
                'email' => $email,
                'name' => 'Applicant',
            ]);
            $guest->id = 0;
            $this->notifications->send($guest, 'hr_guest', $title, $message, [NotificationChannel::Email]);
        } catch (\Throwable $e) {
            Log::warning('HR guest email failed', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }
}
