<?php

namespace App\Enums;

enum EmployeeDocumentCategory: string
{
    // Joining
    case OfferLetter = 'offer_letter';
    case AppointmentLetter = 'appointment_letter';
    case JoiningForm = 'joining_form';
    case Aadhaar = 'aadhaar';
    case Pan = 'pan';
    case AddressProof = 'address_proof';
    case Education = 'education';
    case Experience = 'experience';
    case Photo = 'photo';
    case BankProof = 'bank_proof';
    case PfUanDocument = 'pf_uan_document';
    case EsicDocument = 'esic_document';
    case Nda = 'nda';
    case Declaration = 'declaration';
    case IdCard = 'id_card';

    // During employment
    case LeaveApplication = 'leave_application';
    case AttendanceRecords = 'attendance_records';
    case PerformanceReview = 'performance_review';
    case Promotion = 'promotion';
    case IncrementLetter = 'increment_letter';
    case TransferLetter = 'transfer_letter';
    case Warning = 'warning';
    case Training = 'training';
    case SalaryRevision = 'salary_revision';

    // Exit
    case ResignationForm = 'resignation_form';
    case ResignationAcceptance = 'resignation_acceptance';
    case NoDues = 'no_dues';
    case AssetHandover = 'asset_handover';
    case ExitInterview = 'exit_interview';
    case FullAndFinal = 'full_and_final';
    case ExperienceLetter = 'experience_letter';
    case RelievingLetter = 'relieving_letter';
    case Form16 = 'form_16';
    case PfGratuity = 'pf_gratuity';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, self>
     */
    public static function forStage(EmployeeDocumentStage $stage): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $case->stage() === $stage,
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function valuesForStage(EmployeeDocumentStage $stage): array
    {
        return array_map(fn (self $case) => $case->value, self::forStage($stage));
    }

    /**
     * Documents employees submit themselves (not HR on their behalf).
     *
     * @return array<int, string>
     */
    public static function selfServiceValues(): array
    {
        return [
            self::LeaveApplication->value,
            self::AttendanceRecords->value,
        ];
    }

    /**
     * HR-managed categories for employee document upload in admin.
     *
     * @return array<int, string>
     */
    public static function hrManagedValues(): array
    {
        return array_values(array_diff(
            self::values(),
            array_merge(self::selfServiceValues(), self::valuesForStage(EmployeeDocumentStage::Exit)),
        ));
    }

    public function stage(): EmployeeDocumentStage
    {
        return match ($this) {
            self::OfferLetter,
            self::AppointmentLetter,
            self::JoiningForm,
            self::Aadhaar,
            self::Pan,
            self::AddressProof,
            self::Education,
            self::Experience,
            self::Photo,
            self::BankProof,
            self::PfUanDocument,
            self::EsicDocument,
            self::Nda,
            self::Declaration,
            self::IdCard => EmployeeDocumentStage::Joining,

            self::LeaveApplication,
            self::AttendanceRecords,
            self::PerformanceReview,
            self::Promotion,
            self::IncrementLetter,
            self::TransferLetter,
            self::Warning,
            self::Training,
            self::SalaryRevision => EmployeeDocumentStage::Employment,

            self::ResignationForm,
            self::ResignationAcceptance,
            self::NoDues,
            self::AssetHandover,
            self::ExitInterview,
            self::FullAndFinal,
            self::ExperienceLetter,
            self::RelievingLetter,
            self::Form16,
            self::PfGratuity => EmployeeDocumentStage::Exit,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OfferLetter => 'Offer letter (from company)',
            self::AppointmentLetter => 'Appointment letter',
            self::JoiningForm => 'Joining form',
            self::Aadhaar => 'ID proof — Aadhaar',
            self::Pan => 'ID proof — PAN',
            self::AddressProof => 'Address proof',
            self::Education => 'Educational documents',
            self::Experience => 'Previous company experience / relieving letter',
            self::Photo => 'Passport size photos',
            self::BankProof => 'Cancelled cheque / bank passbook',
            self::PfUanDocument => 'UAN / PF details (if applicable)',
            self::EsicDocument => 'ESIC details (if applicable)',
            self::Nda => 'NDA / confidentiality agreement',
            self::Declaration => 'Employee declaration form',
            self::IdCard => 'Employee ID card',

            self::LeaveApplication => 'Leave application',
            self::AttendanceRecords => 'Attendance records',
            self::PerformanceReview => 'Appraisal / performance form',
            self::Promotion => 'Promotion letter',
            self::IncrementLetter => 'Increment letter',
            self::TransferLetter => 'Transfer letter (if applicable)',
            self::Warning => 'Warning letter (if applicable)',
            self::Training => 'Training certificates',
            self::SalaryRevision => 'Salary revision letter',

            self::ResignationForm => 'Resignation letter',
            self::ResignationAcceptance => 'Resignation acceptance letter',
            self::NoDues => 'No dues form',
            self::AssetHandover => 'Asset handover form (laptop, ID card, etc.)',
            self::ExitInterview => 'Exit interview form',
            self::FullAndFinal => 'Full & final settlement',
            self::ExperienceLetter => 'Experience letter',
            self::RelievingLetter => 'Relieving letter',
            self::Form16 => 'Form 16 (income tax)',
            self::PfGratuity => 'PF / gratuity documents (if applicable)',
        };
    }
}
