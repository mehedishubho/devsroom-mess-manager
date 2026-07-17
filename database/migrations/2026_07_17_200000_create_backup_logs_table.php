<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * backup_logs — one row per backup-surface action (backup / restore-test /
 * download / delete / restore / configure), with success|failure + the
 * captured artisan output / error message.
 *
 * Why this exists: `backup:run` can fail (e.g. mysqldump missing on the
 * server) WITHOUT throwing — Artisan::call() just returns a non-zero exit
 * code, so the old "Backup completed." flash lied. The log makes failures
 * visible on the Backups page instead of vanishing silently. Mirrors the
 * restore_tests table (cross-mess infrastructure — NO mess_id column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            // backup | restore_test | download | delete | restore | configure
            $table->string('action', 32);
            // success | failure
            $table->string('status', 16);
            // The backup zip path for download/delete/restore actions.
            $table->string('path')->nullable();
            // Captured artisan output or exception message — the diagnostic
            // payload that explains WHY a backup failed (mysqldump not found,
            // connection refused, etc.).
            $table->text('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['action', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
