<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_off_requests', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('meal_off_requests', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
