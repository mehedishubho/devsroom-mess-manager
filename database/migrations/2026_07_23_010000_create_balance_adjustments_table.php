<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            // Signed: positive = credit added to the member, negative = charge added.
            $table->decimal('amount', 10, 2);
            $table->string('reason', 500);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mess_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_adjustments');
    }
};
