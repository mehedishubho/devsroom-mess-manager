<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_member_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('monthly_closing_id')->constrained('monthly_closings')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('total_meals', 10, 2);
            $table->decimal('meal_rate', 10, 4);
            $table->decimal('meal_cost', 10, 2);
            $table->decimal('fixed_cost_share', 10, 2);
            $table->decimal('guest_meal_charge', 10, 2);
            $table->decimal('gross_bill', 10, 2);
            $table->decimal('advance_applied', 10, 2)->default(0);
            $table->decimal('net_bill', 10, 2);
            $table->decimal('payments_received', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2);
            $table->timestamps();

            $table->unique(['monthly_closing_id', 'member_id']);
            $table->index(['mess_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_member_summaries');
    }
};
