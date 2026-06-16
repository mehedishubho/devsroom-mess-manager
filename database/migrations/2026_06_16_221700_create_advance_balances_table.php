<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(0);
            $table->timestamp('last_updated_at');
            $table->timestamps();

            $table->unique(['mess_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_balances');
    }
};
