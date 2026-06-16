<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('breakfast')->default(false);
            $table->boolean('lunch')->default(false);
            $table->boolean('dinner')->default(false);
            $table->decimal('guest_breakfast', 4, 2)->default(0);
            $table->decimal('guest_lunch', 4, 2)->default(0);
            $table->decimal('guest_dinner', 4, 2)->default(0);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mess_id', 'member_id', 'date']);
            $table->index(['mess_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_entries');
    }
};
