<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->enum('kind', ['bazar', 'fixed', 'other'])->default('bazar')->after('name');
            $table->index(['mess_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropIndex(['mess_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
