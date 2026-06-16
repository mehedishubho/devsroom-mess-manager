<?php

namespace App\Console\Commands;

use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'mess:create-super-admin
                            {email : Email address of the new super admin}
                            {name : Display name}
                            {--password= : Optional password (auto-generated if not provided)}';

    protected $description = 'Create the initial super-admin user (Tyro super-admin role).';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->option('password') ?? Str::random(24);

        $this->seedTyroRoles();

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $role = Role::where('slug', 'super-admin')->firstOrFail();
        $user->assignRole($role);

        $this->info("Super admin created: {$user->email}");
        if (! $this->option('password')) {
            $this->warn("Temporary password: {$password}");
            $this->warn('Share securely. The user should reset it on first login.');
        }

        return self::SUCCESS;
    }

    private function seedTyroRoles(): void
    {
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        Role::firstOrCreate(['slug' => 'user'], ['name' => 'User']);
    }
}
