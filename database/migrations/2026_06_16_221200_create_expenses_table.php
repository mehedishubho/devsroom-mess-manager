<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->date('date');
            $table->foreignId('purchased_by')->nullable()->constrained('members')->nullOnDelete();
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('expense_type', 20)->default('bazar');
            $table->string('receipt_path')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mess_id', 'date']);
            $table->index(['mess_id', 'expense_type']);
            $table->index(['mess_id', 'expense_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
