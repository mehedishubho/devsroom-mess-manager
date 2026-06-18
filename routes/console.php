<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\Telescope;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// D-06 Pitfall 1 (T-05-01-03): cap telescope_entries growth in dev.
// Wrapped in class_exists so prod (no Telescope via --no-dev) doesn't error.
if (class_exists(Telescope::class)) {
    Schedule::command('telescope:prune')->daily();
}
