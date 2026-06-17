<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_entries', function (Blueprint $table) {
            $table->unique(['mess_id', 'member_id', 'date'], 'meal_entries_mess_member_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('meal_entries', function (Blueprint $table) {
            $table->dropUnique('meal_entries_mess_member_date_unique');
        });
    }
};
