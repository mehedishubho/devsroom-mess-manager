<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['mess_id', 'expense_type']);
            $table->dropColumn('expense_type');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('expense_type', 20)->default('bazar')->after('amount');
            $table->index(['mess_id', 'expense_type']);
        });
    }
};
