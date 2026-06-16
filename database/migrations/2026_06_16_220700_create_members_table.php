<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('mobile', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('nid', 50)->nullable();
            $table->string('profession', 100)->nullable();
            $table->string('room_or_seat', 50)->nullable();
            $table->date('joining_date')->nullable();
            $table->date('leaving_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('emergency_contact', 100)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mess_id', 'status']);
            $table->unique(['mess_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
