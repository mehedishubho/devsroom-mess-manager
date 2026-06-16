<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('monthly_closing_id')->constrained('monthly_closings')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->smallInteger('applied_to_year')->unsigned();
            $table->smallInteger('applied_to_month')->unsigned();
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamps();

            $table->index(['mess_id', 'member_id']);
            $table->index(['applied_to_year', 'applied_to_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_corrections');
    }
};
