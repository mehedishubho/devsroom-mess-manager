<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the dead `enabled_spaces` column from backup_configs.
 *
 * It was introduced for the DigitalOcean Spaces mirror and was never read or
 * written by anything — Spaces support has been removed entirely (backups now
 * go to Local, plus Google Drive / Cloudflare R2 when the operator enables
 * them on the Backups page).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->dropColumn('enabled_spaces');
        });
    }

    public function down(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->boolean('enabled_spaces')->default(false)->after('max_mb');
        });
    }
};
