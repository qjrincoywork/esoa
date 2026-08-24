<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'rm' => [
            'driver' => 'local',
            'root' => env('ROOT_FOLDER') . DIRECTORY_SEPARATOR . 'rm',
        ],
        'billing' => [
            'driver' => 'local',
            'root' => env('ROOT_FOLDER') . DIRECTORY_SEPARATOR . 'billing' . DIRECTORY_SEPARATOR . 'billing_invoices',
        ],
        'concerns' => [
            'driver' => 'local',
            'root' => env('ROOT_FOLDER') . DIRECTORY_SEPARATOR . 'billing' . DIRECTORY_SEPARATOR . 'concerns',
        ],
        'payments' => [
            'driver' => 'local',
            'root' => env('ROOT_FOLDER') . DIRECTORY_SEPARATOR . 'billing' . DIRECTORY_SEPARATOR . 'payments',
        ],
        /*
         * Read-only view of the previous eSOA system's chat_attachments tree, which
         * still holds the files attached to the old remarks/concerns thread. Point
         * LEGACY_CHAT_ATTACHMENTS_ROOT at that directory (a UNC share is fine — the
         * disks above already use one); the app streams from here rather than sending
         * the browser to the legacy host, which it cannot reach. An unset root falls
         * back to an empty local directory, so attachments simply resolve to 404.
         */
        'legacy_chat' => [
            'driver' => 'local',
            'root' => env('LEGACY_CHAT_ATTACHMENTS_ROOT') ?: storage_path('app/private/legacy_chat'),
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
