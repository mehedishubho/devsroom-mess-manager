<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global backup configuration singleton (row id=1). Backups are cross-mess
 * infrastructure (one zip covers the whole DB), so this is NOT scoped to an
 * active mess — unlike the per-mess `settings` table.
 *
 * Holds the admin-configurable knobs surfaced on the Backups page:
 *   - frequency / run_at      → drives the routes/console.php schedule
 *   - keep_all_days / max_mb   → enforced by the backup:purge command
 *   - enabled_spaces           → whether to mirror to DigitalOcean Spaces
 *                                (kept in sync with DO_SPACES_* credential state)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_configs', function (Blueprint $table) {
            $table->id();
            // off | daily | weekly | monthly
            $table->string('frequency', 16)->default('daily');
            $table->time('run_at')->default('01:30:00');
            $table->unsignedInteger('keep_all_days')->default(7);
            $table->unsignedInteger('max_mb')->default(5000);
            $table->boolean('enabled_spaces')->default(false);
            $table->timestamps();
        });

        // Singleton seed: row id=1 always exists so BackupConfig::current()
        // never has to synthesize a missing row.
        DB::table('backup_configs')->insert([
            'id' => 1,
            'frequency' => 'daily',
            'run_at' => '01:30:00',
            'keep_all_days' => 7,
            'max_mb' => 5000,
            'enabled_spaces' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_configs');
    }
};
