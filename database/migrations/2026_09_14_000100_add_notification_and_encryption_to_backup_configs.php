<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the backup notification recipient and archive encryption configurable
 * from the Backups page instead of .env only.
 *
 * - notification_email — where spatie sends failure emails. Overrides the
 *   BACKUP_NOTIFICATION_EMAIL env fallback when set.
 * - encrypt_backups     — explicit on/off for AES-256 client-side archive
 *   encryption (the password is useless without the toggle, and the toggle is
 *   meaningless without a password).
 * - archive_password    — encrypted at rest (TEXT because Laravel's encrypted
 *   cast output exceeds 255 chars). Lives in the DB so it is NOT inside any
 *   backup archive the way an .env value would be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->string('notification_email', 255)->nullable()->after('max_mb');
            $table->boolean('encrypt_backups')->default(false)->after('notification_email');
            $table->text('archive_password')->nullable()->after('encrypt_backups');
        });
    }

    public function down(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->dropColumn(['notification_email', 'encrypt_backups', 'archive_password']);
        });
    }
};
