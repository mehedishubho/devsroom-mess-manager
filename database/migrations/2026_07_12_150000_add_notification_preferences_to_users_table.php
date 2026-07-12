<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user notification channel preferences. Stored as JSON, e.g.
     * {"channels": ["email","telegram"]}. The set is always a subset of the
     * mess's admin-enabled channels; the ChannelManager intersects the two at
     * dispatch time. Null = "no preference set" → user receives every channel
     * the admin has enabled (opt-out model).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('use_gravatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
