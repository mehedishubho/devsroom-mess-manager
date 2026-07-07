<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_disabled_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 255)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mess_id', 'member_id', 'date']);
            $table->index(['member_id', 'date']);
            $table->index(['mess_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_disabled_days');
    }
};
