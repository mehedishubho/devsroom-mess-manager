<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records how long a backup actually took and how big the resulting archive
 * was, on the activity row.
 *
 * Without these the log only ever said "success" — so a backup that quietly
 * went from 4 seconds to 4 minutes, or from 40 MB to 4 GB, looked identical to
 * one that hadn't changed. Both columns are nullable: purge/monitor/download
 * rows have no duration or archive size, and rows written before this
 * migration obviously don't either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_logs', function (Blueprint $table) {
            $table->unsignedInteger('duration_ms')->nullable()->after('message');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('backup_logs', function (Blueprint $table) {
            $table->dropColumn(['duration_ms', 'size_bytes']);
        });
    }
};
