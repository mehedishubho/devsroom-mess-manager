<?php

namespace App\Console\Commands;

use Database\Seeders\PerfDemoSeeder;
use Illuminate\Console\Command;

class SeedPerfDemo extends Command
{
    protected $signature = 'db:seed:perf-demo {--force : Skip the production guard}';

    protected $description = 'Seed the ~50-member demo/perf dataset (D-07). Refuses to run in production unless --force.';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to run PerfDemoSeeder in production. Use --force to override (NOT RECOMMENDED).');

            return self::FAILURE;
        }

        $this->info('Seeding ~50-member demo dataset...');
        $this->call('db:seed', ['--class' => PerfDemoSeeder::class]);
        $this->info('Done. Demo creds: manager@demo.test / member@demo.test (password: "password")');

        return self::SUCCESS;
    }
}
