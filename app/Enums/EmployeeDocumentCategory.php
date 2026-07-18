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
            self::OfferLetter => 'Offer letter (Company provides)',
            self::AppointmentLetter => 'Appointment letter (Company provides)',
            self::JoiningForm => 'Joining form (Company provides)',
            self::Aadhaar => 'ID proof — Aadhaar (Employee submits)',
            self::Pan => 'ID proof — PAN (Employee submits)',
            self::AddressProof => 'Address proof (Employee submits)',
            self::Education => 'Educational documents (Employee submits)',
            self::Experience => 'Previous experience / relieving letter (Employee submits)',
            self::Photo => 'Passport size photos (Employee submits)',
            self::BankProof => 'Cancelled cheque / bank passbook (Employee submits)',
            self::PfUanDocument => 'UAN / PF details (Employee submits)',
            self::EsicDocument => 'ESIC details (Employee submits)',
            self::Nda => 'NDA / confidentiality agreement (Company provides)',
            self::Declaration => 'Employee declaration form (Company provides)',
            self::IdCard => 'Employee ID card (Company provides)',

            self::LeaveApplication => 'Leave application (Employee submits)',
            self::AttendanceRecords => 'Attendance records (Employee submits)',
            self::PerformanceReview => 'Appraisal / performance form (Company provides)',
            self::Promotion => 'Promotion letter (Company provides)',
            self::IncrementLetter => 'Increment letter (Company provides)',
            self::TransferLetter => 'Transfer letter (Company provides)',
            self::Warning => 'Warning letter (Company provides)',
            self::Training => 'Training certificates (Company provides)',
            self::SalaryRevision => 'Salary revision letter (Company provides)',

            self::ResignationForm => 'Resignation letter (Employee submits)',
            self::ResignationAcceptance => 'Resignation acceptance letter (Company provides)',
            self::NoDues => 'No dues form (Company provides)',
            self::AssetHandover => 'Asset handover form (Company provides)',
            self::ExitInterview => 'Exit interview form (Company provides)',
            self::FullAndFinal => 'Full & final settlement (Company provides)',
            self::ExperienceLetter => 'Experience letter (Company provides)',
            self::RelievingLetter => 'Relieving letter (Company provides)',
            self::Form16 => 'Form 16 — income tax (Company provides)',
            self::PfGratuity => 'PF / gratuity documents (Company provides)',
        };
    }

    /**
     * Who is expected to provide this document in the HR process.
     */
    public function providedBy(): EmployeeDocumentProvider
    {
        return match ($this) {
            // Joining — employee submits KYC / personal proofs
            self::Aadhaar,
            self::Pan,
            self::AddressProof,
            self::Education,
            self::Experience,
            self::Photo,
            self::BankProof,
            self::PfUanDocument,
            self::EsicDocument,
            // Self-service
            self::LeaveApplication,
            self::AttendanceRecords,
            // Exit — employee starts with resignation letter
            self::ResignationForm => EmployeeDocumentProvider::Employee,

            // Everything else is issued by the company
            default => EmployeeDocumentProvider::Company,
        };
    }

    /**
     * Process roles that should receive this document by email.
     * Always includes company when non-empty (except leave/attendance).
     *
     * @return array<int, string> company|employee|hr|recruiter|founder|it_admin|accounts|reporting_manager
     */
    public function emailRecipients(): array
    {
        return match ($this) {
            self::LeaveApplication,
            self::AttendanceRecords => [],

            // Joining — Recruiter selects, Founder approves, HR sends offer
            self::OfferLetter => ['company', 'employee', 'hr', 'recruiter', 'founder'],

            // Joining — HR verifies employee documents
            self::Aadhaar,
            self::Pan,
            self::AddressProof,
            self::Education,
            self::Experience,
            self::Photo,
            self::BankProof,
            self::PfUanDocument,
            self::EsicDocument => ['company', 'employee', 'hr'],

            // Joining — HR appointment; IT provisions access; RM assigns project
            self::AppointmentLetter => ['company', 'employee', 'hr', 'it_admin', 'reporting_manager'],

            // Joining — HR onboarding forms
            self::JoiningForm,
            self::Nda,
            self::Declaration => ['company', 'employee', 'hr'],

            // Joining — IT Admin ID / access
            self::IdCard => ['company', 'employee', 'hr', 'it_admin'],

            // Joining / employment — Reporting Manager assign / awareness
            self::Training => ['company', 'employee', 'hr', 'reporting_manager'],

            // Employment letters
            self::PerformanceReview,
            self::Promotion,
            self::IncrementLetter,
            self::TransferLetter,
            self::Warning,
            self::SalaryRevision => ['company', 'employee', 'hr', 'reporting_manager'],

            // Resignation — Employee submits; Manager + HR see it
            self::ResignationForm => ['company', 'employee', 'hr', 'reporting_manager'],

            // Resignation — HR manages exit; Manager for KT/notice
            self::ResignationAcceptance,
            self::ExitInterview => ['company', 'employee', 'hr', 'reporting_manager'],

            // Resignation — IT disables access / assets
            self::NoDues,
            self::AssetHandover => ['company', 'employee', 'hr', 'it_admin', 'reporting_manager'],

            // Resignation — Accounts full & final / tax
            self::FullAndFinal,
            self::Form16,
            self::PfGratuity => ['company', 'employee', 'hr', 'accounts'],

            // Resignation — HR issues letters
            self::ExperienceLetter,
            self::RelievingLetter => ['company', 'employee', 'hr'],
        };
    }

    /**
     * @deprecated Use emailRecipients()
     */
    public function emailAudience(): EmployeeDocumentEmailAudience
    {
        return $this->emailRecipients() === []
            ? EmployeeDocumentEmailAudience::None
            : EmployeeDocumentEmailAudience::CompanyAndMember;
    }
}
