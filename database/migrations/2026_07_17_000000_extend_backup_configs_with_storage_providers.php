<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only extension to backup_configs for per-provider, per-group
 * storage toggles (Task 1 of quick-260717-2q3).
 *
 * The singleton row id=1 picks up the column defaults automatically — no
 * UPDATE statement is needed (defaults are applied at read time for any
 * row that predates this migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->boolean('gdrive_backup')->default(false)->after('enabled_spaces');
            $table->boolean('gdrive_uploads')->default(false)->after('gdrive_backup');
            $table->boolean('r2_backup')->default(false)->after('gdrive_uploads');
            $table->boolean('r2_uploads')->default(false)->after('r2_backup');
        });
    }

    public function down(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->dropColumn(['gdrive_backup', 'gdrive_uploads', 'r2_backup', 'r2_uploads']);
        });
    }
};
