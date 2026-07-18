<?php

namespace App\Enums;

enum EmployeeDocumentProvider: string
{
    /** Company issues this document to the employee */
    case Company = 'company';

    /** Employee must submit this document to the company */
    case Employee = 'employee';
}
