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

    'default_pages' => 10,
    'max_per_pages' => 100,
    'file_preview_token_ttl_minutes' => 10,//FILE_PREVIEW_TOKEN_TTL_MINUTES
    'max_string_limit' => 191,
    'max_file_size' => 2048, // in KB
    'max_text_limit' => 800,
    'default_password' => 'VALUCARE123',
    'contact_email' => 'billingsection@valuecarehealth.com',
    'billing_notification_email' => env('BILLING_NOTIFICATION_EMAIL', 'billingsection@valuecarehealth.com'),
    'contact_number' => '+639123456789',
    'ignored_diff_keys' => ['created_at', 'updated_at', 'deleted_at'],
];
