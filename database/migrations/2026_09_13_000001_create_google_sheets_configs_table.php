<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sheets_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('auth_mode', 20)->default('service_account');

            // Credentials. Secrets are encrypted at rest via the model's casts,
            // so they need a text column (ciphertext is far longer than the key).
            $table->text('service_account_json')->nullable();
            $table->string('service_account_email')->nullable();
            $table->string('oauth_client_id')->nullable();
            $table->text('oauth_client_secret')->nullable();
            $table->text('oauth_refresh_token')->nullable();

            // Target spreadsheet + which tabs are mirrored (model class => bool).
            $table->string('spreadsheet_id')->nullable();
            $table->json('tabs')->nullable();

            // Sync status surfaced on the config screen.
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->timestamps();

            $table->unique('mess_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheets_configs');
    }
};
