<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dirty-row buffer: one row per (mess, model, record). A second change
        // to the same record upserts in place, so a burst of writes (e.g. a
        // meal-grid save) coalesces into a single flush instead of one API call
        // per row.
        Schema::create('google_sheets_pending', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->string('model');
            $table->unsignedBigInteger('record_id');
            $table->string('operation', 10);
            $table->timestamps();

            $table->unique(['mess_id', 'model', 'record_id']);
            $table->index(['mess_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheets_pending');
    }
};
