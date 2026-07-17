<?php

namespace App\Enums;

enum ApplicationDocumentType: string
{
    case Resume = 'resume';
    case Photo = 'photo';
    case Aadhaar = 'aadhaar';
    case Pan = 'pan';
    case EducationCertificates = 'education_certificates';
    case ExperienceCertificates = 'experience_certificates';
    case SalarySlips = 'salary_slips';
    case BankProof = 'bank_proof';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Resume => 'Resume (CV)',
            self::Photo => 'Passport size photo',
            self::Aadhaar => 'Aadhaar card',
            self::Pan => 'PAN card',
            self::EducationCertificates => 'Educational certificates',
            self::ExperienceCertificates => 'Experience certificate',
            self::SalarySlips => 'Last 3 months salary slips',
            self::BankProof => 'Bank details (passbook / cancelled cheque)',
        };
    }

    public function hint(): ?string
    {
        return match ($this) {
            self::ExperienceCertificates => 'Required if you are an experienced candidate',
            self::SalarySlips => 'For experienced candidates',
            default => null,
        };
    }

    public function required(): bool
    {
        return $this === self::Resume;
    }
}
