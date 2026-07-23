<?php

use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the vestigial `admin` role. The real manager-level role is `manager`
 * (created by 2026_06_20_000000_create_manager_role_and_mess_privileges, which
 * also grants it the mess-management privileges). Any account still holding the
 * old `admin` slug is moved to `manager` first so no manager is locked out.
 *
 * Uses Role->users()/privileges() relationships (not a hardcoded pivot table)
 * because Tyro's pivot table name is configurable — default is `user_roles`.
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

        // Reassign every user that holds the `admin` role to `manager`.
        foreach ($admin->users as $managerUser) {
            $managerUser->roles()->syncWithoutDetaching([$manager->id]);
        }

        $admin->users()->detach();
        $admin->privileges()->detach();
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
