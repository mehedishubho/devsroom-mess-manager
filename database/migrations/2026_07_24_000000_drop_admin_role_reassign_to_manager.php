<?php

use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the vestigial `admin` role. The real manager-level role is `manager`
 * (created by 2026_06_20_000000_create_manager_role_and_mess_privileges, which
 * also grants it the mess-management privileges). Any account still holding the
 * old `admin` slug is moved to `manager` first so no manager is locked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        $admin = Role::where('slug', 'admin')->first();
        if (! $admin) {
            return; // role already gone
        }

        $manager = Role::firstOrCreate(
            ['slug' => 'manager'],
            ['name' => 'Manager']
        );

        // Reassign every admin role-user to manager (idempotent).
        $userIds = DB::table('role_user')->where('role_id', $admin->id)->pluck('user_id');
        foreach ($userIds as $uid) {
            DB::table('role_user')->updateOrInsert([
                'user_id' => $uid,
                'role_id' => $manager->id,
            ]);
        }

        try {
            $admin->users()->detach();
            $admin->privileges()->detach();
        } catch (\Throwable) {
            DB::table('role_user')->where('role_id', $admin->id)->delete();
        }

        $admin->delete();
    }

    public function down(): void
    {
        Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator']
        );
    }
};
