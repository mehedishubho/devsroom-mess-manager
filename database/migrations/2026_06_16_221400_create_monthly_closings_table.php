<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->smallInteger('year')->unsigned();
            $table->smallInteger('month')->unsigned();
            $table->decimal('total_bazar', 12, 2);
            $table->decimal('total_fixed_expense', 12, 2);
            $table->decimal('total_meals', 12, 2);
            $table->decimal('meal_rate', 10, 4);
            $table->integer('member_count');
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->constrained('users');
            $table->string('status', 20)->default('closed');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['mess_id', 'year', 'month']);
            $table->index(['mess_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_closings');
    }
};
