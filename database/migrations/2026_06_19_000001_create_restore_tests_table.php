<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-04 restore_tests table — one row per restore-test run.
 *
 * NO mess_id column by design: restore-tests are cross-mess infrastructure
 * (they validate the full DB dump, not a single mess). The latest row drives
 * the health badge in Plan 06-03's Backups UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_tests', function (Blueprint $table) {
            $table->bigIncrements('id');
            // 'passed' | 'failed' | 'running' | 'error'
            $table->string('status', 32);
            // [{table, live, test, pass}, ...] — per-table COUNT comparison.
            $table->json('per_table_counts')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index('ran_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_tests');
    }
};
