<?php

use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the generic `user` role. New (and existing) mess members use the
 * `mess-member` role, which already carries the member privileges (created by
 * 2026_07_07_034237_create_mess_member_role_and_privileges). Every account that
 * currently holds `user` is moved to `mess-member` first so no one is locked out.
 *
 * Uses the Role->users()/privileges() relationships (not a hardcoded pivot table)
 * because Tyro's pivot table name is configurable — default is `user_roles`.
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

        // Reassign every user that holds the `user` role to `mess-member`.
        foreach ($user->users as $member) {
            $member->roles()->syncWithoutDetaching([$messMember->id]);
        }

        // Detach + delete the user role.
        $user->users()->detach();
        $user->privileges()->detach();
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
