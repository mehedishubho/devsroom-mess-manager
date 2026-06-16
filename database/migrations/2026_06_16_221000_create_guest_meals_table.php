<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('guest_name');
            $table->date('date');
            $table->string('meal_type', 20);
            $table->decimal('quantity', 4, 2)->default(1);
            $table->decimal('meal_value', 4, 2);
            $table->decimal('charge_amount', 10, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mess_id', 'date']);
            $table->index(['mess_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_meals');
    }
};
