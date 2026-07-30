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
    'app_name' => env('APP_NAME', 'eSOA'),
    'api_key' => env('VC_API_KEY'),
    'api_auth_token' => 'API_AUTH_TOKEN',

    'superadmin' => 'superadmin',
    'chunk_size' => 2000,
    'billing_reminder_time' => env('BILLING_REMINDER_TIME', '07:00'), // Default to 7:00 AM if not set
    'overlapping_timeout' => env('BILLING_REMINDER_TIMEOUT', 3600), // Default to 1 hour if not set
    'peso_sign' => '₱',
    'default_pages' => 10,
    'max_per_pages' => 100,
    'bulk_import_max_rows' => (int) env('BULK_IMPORT_MAX_ROWS', 1000),
    'soa_export_max_rows' => 7000,
    'file_preview_token_ttl_minutes' => 10,//FILE_PREVIEW_TOKEN_TTL_MINUTES
    'min_username_string_limit' => 3,
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

    /*
    | Roles that must have confirmed two-factor authentication before they can
    | use the app (enforced by App\Http\Middleware\EnsureTwoFactorEnabled).
    | Set ENFORCE_2FA_ROLES to a comma-separated list, or empty to disable.
    */
    'enforce_2fa_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ENFORCE_2FA_ROLES', 'superadmin,billing_admin'))
    ))),

    'uploads_folder' => env('UPLOADS_FOLDER'),
    'billing_disk' => env('BILLING_DISK', 'billing'),

    /*
    |--------------------------------------------------------------------------
    | Storage disks (private, off web root)
    |--------------------------------------------------------------------------
    |
    | Disk selection MUST be resolved through config(), never env(), so that it
    | survives `php artisan config:cache` (env() returns null once config is
    | cached, which previously collapsed these to the world-readable 'public'
    | disk). Defaults point at the private disks defined in config/filesystems.php.
    |
    */
    'disks' => [
        'rm' => env('RM_DISK', 'rm'),
        'billing' => env('BILLING_DISK', 'billing'),
        'concerns' => env('CONCERNS_DISK', 'concerns'),
        'account_payments' => env('ACCOUNT_PAYMENTS_DISK', 'payments'),
    ],
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
    'reserved_usernames' => [
        'admin',
        'administrator',
        'root',
        'system',
        'support',
        'api',
        'null',
        'guest',
        'anonymous',
        'superadmin',
    ],
];
