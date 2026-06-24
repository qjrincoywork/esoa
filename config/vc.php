<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VC API KEY
    |--------------------------------------------------------------------------
    |
    | The API Key
    |
    */
    'api_key' => env('VC_API_KEY'),
    'api_auth_token' => 'API_AUTH_TOKEN',

    'chunk_size' => 2000,
    'billing_reminder_time' => env('BILLING_REMINDER_TIME', '07:00'), // Default to 7:00 AM if not set
    'overlapping_timeout' => env('BILLING_REMINDER_TIMEOUT', 3600), // Default to 1 hour if not set
    'peso_sign' => '₱',
    'default_pages' => 10,
    'max_per_pages' => 100,
    'soa_export_max_rows' => 7000,
    'file_preview_token_ttl_minutes' => 10,//FILE_PREVIEW_TOKEN_TTL_MINUTES
    'max_string_limit' => 191,
    'max_file_size' => 2048, // 2MB in KB
    'max_text_limit' => 800,
    'default_password' => null, // Deprecated — new users receive a randomly generated password
    'temp_password_expires_hours' => (int) env('TEMP_PASSWORD_EXPIRES_HOURS', 72),
    'contact_email' => 'esoabillingandcollection@valuecarehealth.com',
    'billing_notification_email' => env('BILLING_NOTIFICATION_EMAIL', 'esoabillingandcollection@valuecarehealth.com'),
    'contact_number' => '+639123456789',
    'ignored_diff_keys' => ['created_at', 'updated_at', 'deleted_at'],
    'allowed_soa_status_for_account_branch_admin' => [2, 4],
    'uploads_folder' => env('UPLOADS_FOLDER'),
    'billing_disk' => env('BILLING_DISK', 'billing'),
    'soa_import' => [
        'chunk_size' => (int) env('SOA_IMPORT_CHUNK_SIZE', 2000),
        'limit' => ($limit = env('SOA_IMPORT_LIMIT')) !== null && $limit !== ''
            ? (int) $limit
            : null,
        'status' => (int) env('SOA_IMPORT_STATUS', 0),
        'date_from' => env('SOA_IMPORT_DATE_FROM', '2025-01-01'),
        'date_to' => env('SOA_IMPORT_DATE_TO', '2026-12-30'),
        'poc_start_from' => env('SOA_IMPORT_POC_START_FROM', '2023-01-01'),
    ],
    'billing_invoice_export_headers' => [
        'Billing Invoice',
        'Account Code',
        'Account',
        'Branch Code',
        'Branch',
        'Account Type',
        'Billing Ref',
        'Bill Date',
        'Due Date',
        'Due In',
        'Amount',
        'Status',
        'Period Start Date',
        'Period End Date',
        'Contract Start Date',
        'Contract End Date',
    ],
];
