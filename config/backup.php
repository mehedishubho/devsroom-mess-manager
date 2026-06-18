<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

return [

    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('APP_NAME', 'devsroom-mess'),

        'source' => [
            'files' => [
                /*
                 * The list of directories and files that will be included in the backup.
                 *
                 * D-07: back up ONLY storage/app/public (profile photos + bazar receipts).
                 * The MySQL dump is added by spatie regardless of this list.
                 */
                'include' => [
                    storage_path('app/public'),
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * D-07: secrets (`.env`) must NEVER cross the trust boundary into object storage.
                 * Pitfall 4/8: storage/framework + spatie's own working area + build deps excluded.
                 */
                'exclude' => [
                    storage_path('app/backup-temp'),
                    storage_path('app/laravel-backup'),
                    storage_path('app/private'),
                    storage_path('framework'),
                    base_path('.env'),
                    base_path('vendor'),
                    base_path('node_modules'),
                ],

                /*
                 * Determines if symlinks should be followed.
                 *
                 * Pitfall 4: do NOT follow the public/storage symlink — the real files
                 * live under storage/app/public/ which is already in `include` above.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * This path is used to make directories in resulting zip-file relative.
                 * Set to `null` to include complete absolute path.
                 */
                'relative_path' => storage_path('app/public'),
            ],

            /*
             * The names of the connections to the databases that should be backed up.
             * MySQL only — the active connection (config/database.php).
             *
             * The mysqldump behavior is customized via the 'dump' key on the mysql
             * connection in config/database.php (DUMP_BINARY_PATH, single-transaction).
             */
            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        /*
         * The database dump can be compressed to decrease disk space usage.
         */
        'database_dump_compressor' => null,

        /*
         * If specified, the database dumped file name will contain a timestamp (e.g.: 'Y-m-d-H-i-s').
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The base of the dump filename, either 'database' or 'connection'.
         */
        'database_dump_filename_base' => 'database',

        /*
         * The file extension used for the database dump files.
         * Empty = package default (.sql for MySQL).
         */
        'database_dump_file_extension' => '',

        'destination' => [
            /*
             * The compression algorithm used for creating the zip archive.
             */
            'compression_method' => ZipArchive::CM_DEFAULT,

            /*
             * The compression level corresponding to the used algorithm (0-9).
             */
            'compression_level' => 9,

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',

            /*
             * The disk names on which the backups will be stored.
             *
             * D-02: S3-compatible DO Spaces destination via the `backups` disk.
             */
            'disks' => [
                env('BACKUP_DISK', 'backups'),
            ],

            /*
             * Determines whether to allow backups to continue when some targets fail.
             */
            'continue_on_failure' => false,
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * The password to be used for archive encryption.
         * Set to `null` to disable encryption.
         *
         * Pitfall 8 / Security V6: optional AES-256 client-side encryption layer.
         * DO Spaces already provides server-side encryption at rest; this is belt-and-suspenders.
         * Operator supplies a strong value in prod via BACKUP_ARCHIVE_PASSWORD; empty = no encryption.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * Set to 'none' to disable encryption.
         *
         * Supported: 'none', 'default', 'aes128', 'aes192', 'aes256'
         * 'default' = AES-256 when available.
         */
        'encryption' => env('BACKUP_ARCHIVE_ENCRYPTION', 'default'),

        /*
         * After creating the zip, verify it can be opened and contains files.
         * Recommended for critical backups but adds a small overhead.
         */
        'verify_backup' => false,

        /*
         * The number of attempts, in case the backup command encounters an exception.
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new backup if the previous try failed.
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail'.
     *
     * D-05: notify super-admin on backup failure / unhealthy state.
     */
    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            HealthyBackupWasFoundNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => ['mail'],
            CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        /*
         * This class will be used to send all notifications.
         */
        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS', 'backups@example.com')),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'backups@example.com'),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'devsroom-mess')),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        /*
         * A generic webhook channel that POSTs JSON to a URL.
         */
        'webhook' => [
            'url' => '',
        ],
    ],

    /*
     * The log channel used for backup activity messages.
     */
    'log_channel' => null,

    /*
     * Here you can specify which backups should be monitored.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'devsroom-mess'),
            'disks' => [env('BACKUP_DISK', 'backups')],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    /*
     * Here you can configure the cleanup tasks for the backups.
     *
     * D-02 retention ladder: keep everything 7d, daily 14d, weekly 8w, monthly 12mo, yearly 2y.
     * Long monthly retention exists because monthly_closings snapshots are immutable
     * financial records — corruption discovered months later must still be recoverable.
     */
    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => 7,

            /*
             * D-02: daily backups retained 14 days.
             */
            'keep_daily_backups_for_days' => 14,

            'keep_weekly_backups_for_weeks' => 8,

            /*
             * D-02: monthly backups retained 12 months (immutable financial records).
             */
            'keep_monthly_backups_for_months' => 12,

            'keep_yearly_backups_for_years' => 2,

            /*
             * T-06-01-04: unbounded growth guard.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => env('BACKUP_MAX_MB', 5000),
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception.
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new cleanup if the previous try failed.
         */
        'retry_delay' => 0,
    ],

];
