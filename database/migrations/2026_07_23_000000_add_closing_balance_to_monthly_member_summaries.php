<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_member_summaries', function (Blueprint $table) {
            // Signed running balance FROZEN at close: positive = the member closed
            // the month in credit, negative = in debt. Nullable so pre-existing
            // snapshots (frozen before this column existed) keep working — read
            // paths fall back to the month residual when null.
            $table->decimal('closing_balance', 10, 2)->nullable()->after('balance_due');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_member_summaries', function (Blueprint $table) {
            $table->dropColumn('closing_balance');
        });
    }
};
