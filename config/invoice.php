<?php

/**
 * Invoice branding — keep in sync with frontend/src/lib/invoiceConfig.ts
 */
return [
    'company' => [
        'name' => env('INVOICE_COMPANY_NAME', ''),
        'tagline' => env('INVOICE_COMPANY_TAGLINE', ''),
        'address' => env('INVOICE_COMPANY_ADDRESS', ''),
        'email' => env('INVOICE_BILLING_EMAIL', ''),
        'website' => env('INVOICE_COMPANY_WEBSITE', ''),
        'phone' => env('INVOICE_COMPANY_PHONE', ''),
        'account_no' => env('INVOICE_ACCOUNT_NO', ''),
        'account_name' => env('INVOICE_ACCOUNT_NAME', ''),
        'ifsc_code' => env('INVOICE_IFSC_CODE', ''),
        'upi_vpa' => env('INVOICE_UPI_VPA', ''),
        'branch' => env('INVOICE_BRANCH', ''),
        'signatory' => env('INVOICE_SIGNATORY', ''),
        'gst_number' => env('GST_NUMBER', ''),
        'initials' => env('INVOICE_INITIALS', ''),
    ],
    'gst_rate' => (float) env('GST_RATE', 18),
    'invoice_prefix' => env('INVOICE_PREFIX', 'INV'),
    'invoice_number_start' => (int) env('INVOICE_NUMBER_START', 1),
    'terms' => env('INVOICE_TERMS', ''),
    'colors' => [
        'navy' => '#0f2854',
        'blue' => '#1e40af',
        'teal' => '#14b8a6',
        'aqua' => '#2dd4bf',
        'primary' => '#1e40af',
        'accent' => '#1e40af',
        'border' => '#e2e8f0',
        'text' => '#334155',
        'text_muted' => '#64748b',
        'table_head' => '#1e40af',
    ],
];
