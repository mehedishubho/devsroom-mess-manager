<?php

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
    Schedule::command('backup:clean')->daily()->at('01:00')->onOneServer();
    Schedule::command('backup:run')->daily()->at('01:30')
        ->withoutOverlapping()->onOneServer();
    Schedule::command('backup:monitor')->daily()->at('02:00')->onOneServer();

    // D-04: nightly restore-test. Tunable — change cadence here to weekly if
    // VPS load matters. Guarded on spatie (WR-07): the RestoreTestRun command
    // ships in the app regardless, but the test needs a spatie backup zip to
    // load, so it is meaningless without the backup pipeline.
    Schedule::command('backup:restore-test')->daily()->at('03:00')
        ->withoutOverlapping()->onOneServer();
}
