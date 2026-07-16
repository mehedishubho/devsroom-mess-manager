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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
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

        // Local backup copy — ALWAYS present. The BackupController lists,
        // downloads, restores, and deletes against this disk. Backups land here
        // first (default destination) regardless of cloud config.
        'backups-local' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'throw' => true,
        ],

        // Off-server mirror — DigitalOcean Spaces (S3-compatible), D-02.
        // Separate from the general-purpose `s3` disk so the spatie default is untouched.
        // Pitfall 5: DO_SPACES_REGION MUST match the DO_SPACES_ENDPOINT subdomain (nyc3 + https://nyc3.digitaloceanspaces.com).
        //
        // This disk is ONLY added to spatie's destination list when DO_SPACES_* credentials
        // are fully set — see App\Support\BackupDestinations. It is never used for listing,
        // so an unconfigured Spaces (empty key/secret) never triggers the AWS SDK's EC2
        // instance-metadata probe (169.254.169.254).
        'backups' => [
            'driver' => 's3',
            'key' => env('DO_SPACES_KEY'),
            'secret' => env('DO_SPACES_SECRET'),
            'region' => env('DO_SPACES_REGION', 'nyc3'),
            'bucket' => env('DO_SPACES_BUCKET'),
            'endpoint' => env('DO_SPACES_ENDPOINT', 'https://nyc3.digitaloceanspaces.com'),
            'use_path_style_endpoint' => env('DO_SPACES_USE_PATH_STYLE_ENDPOINT', false),
            // Surface upload errors instead of silently swallowing them.
            'throw' => true,
        ],

        // Google Drive backup destination (Task 1 — quick-260717-2q3).
        // The driver 'google-drive' is registered by AppServiceProvider via
        // Storage::extend() with a class_exists guard so the app boots cleanly
        // even when masbug/flysystem-google-drive-ext is not installed.
        'backups-gdrive' => [
            'driver' => 'google-drive',
            'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
            'folderId' => env('GOOGLE_DRIVE_FOLDER_ID'),
            'throw' => false,
        ],

        // Google Drive uploads-mirror destination (Task 1). Same creds; the
        // StorageProvider mirror layer namespaces keys by subpath so backups
        // and uploads never collide within the shared Drive folder.
        'uploads-gdrive' => [
            'driver' => 'google-drive',
            'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
            'folderId' => env('GOOGLE_DRIVE_FOLDER_ID'),
            'throw' => false,
        ],

        // Cloudflare R2 backup destination (S3-compatible endpoint, Task 1).
        // Reuses the already-installed league/flysystem-aws-s3-v3 driver — no
        // new package needed. 'region' => 'auto' is the documented R2 value.
        'backups-r2' => [
            'driver' => 's3',
            'key' => env('R2_KEY'),
            'secret' => env('R2_SECRET'),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // Cloudflare R2 uploads-mirror destination (Task 1).
        'uploads-r2' => [
            'driver' => 's3',
            'key' => env('R2_KEY'),
            'secret' => env('R2_SECRET'),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
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
