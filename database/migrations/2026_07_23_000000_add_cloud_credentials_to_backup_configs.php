<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Google Drive + Cloudflare R2 cloud-backup credentials to backup_configs
 * so a super-admin can configure them from the /dashboard/backups UI instead
 * of editing .env. DO Spaces stays env-only (already the working default).
 *
 * Secret fields (gdrive_client_secret, gdrive_refresh_token, r2_secret) are
 * stored as TEXT because Laravel's `encrypted` cast stores a base64'd
 * ciphertext (payload + IV + MAC) that can exceed 255 chars. The BackupConfig
 * model casts those three to `encrypted` — they are NEVER readable as
 * plaintext in the DB. This matters because backups include the DB dump:
 * encrypted-at-rest keeps creds out of every off-site backup zip, while the
 * decrypt key (APP_KEY) stays in the excluded .env (D-07).
 *
 * Identifiers (client id, folder id, key, region, bucket, endpoint) are
 * non-secret and stored as plain varchar. Singleton row id=1 picks up the
 * column defaults automatically — no UPDATE statement needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            // Google Drive
            $table->string('gdrive_client_id', 255)->nullable()->after('r2_uploads');
            $table->text('gdrive_client_secret')->nullable()->after('gdrive_client_id');
            $table->text('gdrive_refresh_token')->nullable()->after('gdrive_client_secret');
            $table->string('gdrive_folder_id', 255)->nullable()->after('gdrive_refresh_token');

            // Cloudflare R2
            $table->string('r2_key', 255)->nullable()->after('gdrive_folder_id');
            $table->text('r2_secret')->nullable()->after('r2_key');
            $table->string('r2_region', 32)->default('auto')->after('r2_secret');
            $table->string('r2_bucket', 255)->nullable()->after('r2_region');
            $table->string('r2_endpoint', 255)->nullable()->after('r2_bucket');
            $table->boolean('r2_use_path_style')->default(false)->after('r2_endpoint');
        });
    }

    public function down(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->dropColumn([
                'gdrive_client_id',
                'gdrive_client_secret',
                'gdrive_refresh_token',
                'gdrive_folder_id',
                'r2_key',
                'r2_secret',
                'r2_region',
                'r2_bucket',
                'r2_endpoint',
                'r2_use_path_style',
            ]);
        });
    }
};
