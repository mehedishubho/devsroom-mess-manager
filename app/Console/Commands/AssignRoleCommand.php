<?php

namespace App\Console\Commands;

use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Console\Command;

class AssignRoleCommand extends Command
{
    protected $signature = 'mess:assign-role
                            {email : User email}
                            {role : Role slug (super-admin, admin, manager, user)}
                            {--sync : Detach all other roles first (replace)}';

    protected $description = 'Assign a Tyro role to an existing user.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error("No user with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $role = Role::where('slug', $this->argument('role'))->first();
        if (! $role) {
            $this->error("No role with slug {$this->argument('role')}.");

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $user->syncRoles([$role]);
            $this->info("Synced role '{$role->slug}' to {$user->email} (other roles detached).");
        } else {
            $user->assignRole($role);
            $this->info("Assigned role '{$role->slug}' to {$user->email}.");
        }

        return self::SUCCESS;
    }
}
