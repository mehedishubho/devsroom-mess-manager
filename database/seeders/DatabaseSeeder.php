<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ExpenseCategorySeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@user.com',
        ]);

        // D-07: PerfDemoSeeder is intentionally NOT called here.
        // Run explicitly via:
        //   php artisan db:seed --class=PerfDemoSeeder
        // OR the env-guarded `php artisan db:seed:perf-demo` command.
        // NEVER run in production.
    }
}
