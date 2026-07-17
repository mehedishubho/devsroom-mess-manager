<?php

use App\Models\BackupConfig;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\Telescope;
use Spatie\Backup\BackupServiceProvider;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// D-06 Pitfall 1 (T-05-01-03): cap telescope_entries growth in dev.
// Wrapped in class_exists so prod (no Telescope via --no-dev) doesn't error.
if (class_exists(Telescope::class)) {
    Schedule::command('telescope:prune')->daily();
}

// D-05: nightly backup pipeline (research Pattern 8). Mirrors the
// telescope:prune class_exists guard. onOneServer requires the database
// cache store (already in use). backup:run + backup:restore-test use
// withoutOverlapping so a slow run cannot double up.
if (class_exists(BackupServiceProvider::class)) {
    // Retention rotation is DB-driven (admin-configurable keep_days + storage
    // cap), so it replaces spatie's backup:clean — which only reads static config.
    Schedule::command('backup:purge')->daily()->at('01:00')->onOneServer();

    // Backup cadence is admin-configurable via BackupConfig (off/daily/weekly/monthly + time).
    // The scheduler is rebuilt each schedule:run, so a change takes effect within a minute.
    $cfg = BackupConfig::current();

    if (in_array($cfg->frequency, ['daily', 'weekly', 'monthly'], true)) {
        Schedule::command('backup:run')
            ->{$cfg->frequency}()
            ->at($cfg->runAtLabel())
            ->withoutOverlapping()
            ->onOneServer();
    }
    Schedule::command('backup:monitor')->daily()->at('02:00')->onOneServer();

    // D-04: nightly restore-test. Tunable — change cadence here to weekly if
    // VPS load matters. Guarded on spatie (WR-07): the RestoreTestRun command
    // ships in the app regardless, but the test needs a spatie backup zip to
    // load, so it is meaningless without the backup pipeline. Skipped entirely
    // when BACKUP_RESTORE_TEST_ENABLED=false (shared hosting without a scratch DB).
    if (config('backup.restore_test_enabled', true)) {
        Schedule::command('backup:restore-test')->daily()->at('03:00')
            ->withoutOverlapping()->onOneServer();
    }
}
