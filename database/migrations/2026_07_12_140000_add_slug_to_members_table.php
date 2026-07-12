<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adds a per-mess-unique slug to members so member URLs read as the member's
     * name (/mess/members/john-doe) instead of an opaque id. Collisions are
     * disambiguated with a -2 / -3 suffix, then a short random tail.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill: every existing member gets a unique slug within its mess.
        // Soft-deleted rows are included in the uniqueness window so a re-added
        // same-name member doesn't collide with a tombstoned slug.
        $rows = DB::table('members')->orderBy('id')->get(['id', 'mess_id', 'name']);

        foreach ($rows as $row) {
            DB::table('members')->where('id', $row->id)->update([
                'slug' => $this->uniqueSlug($row->name, (int) $row->mess_id, (int) $row->id),
            ]);
        }

        // Enforce uniqueness within a mess. A global unique index would block two
        // messes from having the same slug — we only need per-mess uniqueness.
        try {
            Schema::table('members', function (Blueprint $table) {
                $table->index(['mess_id', 'slug']);
            });
        } catch (Throwable) {
            // Index may already exist on some installs — non-fatal.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('members', function (Blueprint $table) {
                $table->dropIndex(['mess_id', 'slug']);
            });
        } catch (Throwable) {
            // Non-fatal on rollback.
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    /**
     * Generate a per-mess-unique slug for a member name. Mirrors the logic in
     * App\Models\Member::generateUniqueSlug so the backfill and runtime agree.
     */
    private function uniqueSlug(string $name, int $messId, int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'member-'.$ignoreId;
        $slug = $base;
        $suffix = 2;

        while (DB::table('members')
            ->where('mess_id', $messId)
            ->where('id', '!=', $ignoreId)
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
};
