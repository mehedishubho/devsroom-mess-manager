<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mess_closed_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['mess_id', 'date']);
            $table->index(['mess_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_closed_days');
    }
};
