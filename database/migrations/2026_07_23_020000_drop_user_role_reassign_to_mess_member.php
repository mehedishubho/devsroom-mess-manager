<?php

use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the generic `user` role. New (and existing) mess members use the
 * `mess-member` role, which already carries the member privileges (created by
 * 2026_07_07_034237_create_mess_member_role_and_privileges). Every account that
 * currently holds `user` is moved to `mess-member` first so no one is locked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        $user = Role::where('slug', 'user')->first();
        if (! $user) {
            return; // nothing to do — role already gone
        }

        $messMember = Role::firstOrCreate(
            ['slug' => 'mess-member'],
            ['name' => 'Mess Member']
        );

        // Reassign every user_role pivot row to mess-member (idempotent).
        $userIds = DB::table('role_user')->where('role_id', $user->id)->pluck('user_id');
        foreach ($userIds as $uid) {
            DB::table('role_user')->updateOrInsert([
                'user_id' => $uid,
                'role_id' => $messMember->id,
            ]);
        }

        // Detach + delete the user role.
        try {
            $user->users()->detach();
            $user->privileges()->detach();
        } catch (\Throwable) {
            // Pivot table names vary across Tyro versions — fall back to a raw
            // delete on the standard role_user pivot if the relations miss.
            DB::table('role_user')->where('role_id', $user->id)->delete();
        }

        $user->delete();
    }

    public function down(): void
    {
        Role::firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User']
        );
    }
};
