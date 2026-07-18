<?php

namespace App\Enums;

/**
 * Who should receive email when an HR document is uploaded.
 */
enum EmployeeDocumentEmailAudience: string
{
    /** Company inbox + the employee this document belongs to */
    case CompanyAndMember = 'company_and_member';

    /** Do not email on upload */
    case None = 'none';
}
