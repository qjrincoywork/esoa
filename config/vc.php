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
        // Files attached to the legacy remarks thread; rooted at the old system's
        // chat_attachments directory (see LEGACY_CHAT_ATTACHMENTS_ROOT).
        'legacy_chat' => env('LEGACY_CHAT_DISK', 'legacy_chat'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Batch billing-invoice upload
    |--------------------------------------------------------------------------
    |
    | The ceiling on a whole manifest — how many rows/attachments one batch-upload
    | wizard session may contain in total, across every request it takes to send it.
    |
    | This used to double as the per-request limit, back when a batch was always
    | exactly one HTTP request; the default was read from max_file_uploads so the
    | app and PHP could never disagree about it (a value above max_file_uploads
    | would make PHP silently drop attachments past that count, and the importer
    | would then report perfectly good rows as missing their files).
    |
    | The client now splits a large manifest into several requests sized to fit
    | under the server's real max_file_uploads / post_max_size (see
    | SoaController::batchCreate()'s `php_limits`), so this default no longer needs
    | to track max_file_uploads — it stays here mainly as protection against an
    | absurdly large upload rather than a value the client must not exceed in one go.
    |
    */
    'soa_batch' => [
        'max_attachments' => (int) env('SOA_BATCH_MAX_ATTACHMENTS', (int) ini_get('max_file_uploads') ?: 20),
        'max_rows' => (int) env('SOA_BATCH_MAX_ROWS', (int) floor(((int) ini_get('max_file_uploads') ?: 20) / 2)),
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
        'Uploaded Date',
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
