<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RestoreTestService;
use Illuminate\Console\Command;
use Throwable;

/**
 * D-04 restore-test artisan command. Loads the latest backup into the
 * mysql_restore_test scratch DB and asserts per-table COUNT(*) parity.
 *
 * Scheduled nightly (03:00) in routes/console.php with withoutOverlapping +
 * onOneServer. Can also be invoked on demand by a super-admin via the
 * Backups UI in Plan 06-03.
 */
class RestoreTestRun extends Command
{
    protected $signature = 'backup:restore-test';

    protected $description = 'Run the periodic restore-test (D-04): load the latest backup into the mysql_restore_test scratch DB and assert per-table COUNT(*) parity.';

    public function handle(RestoreTestService $service): int
    {
        $this->info('Running restore-test against the latest backup...');

        try {
            $result = $service->runLatest();

            if ($result->status === 'passed') {
                $this->info("Restore-test PASSED (ran at {$result->ran_at}).");

                return self::SUCCESS;
            }

            $this->error("Restore-test {$result->status}: {$result->message}");

            return self::FAILURE;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $hint = '';
            if (preg_match('/Access denied|Unknown database|Unknown column/i', $msg)) {
                $db = config('database.connections.mysql_restore_test.database', 'devsroom_mess_restore_test');
                $hint = " The restore-test scratch database '{$db}' is missing or the MySQL user lacks privileges on it. Either create it + GRANT the app user, or disable the test with BACKUP_RESTORE_TEST_ENABLED=false.";
            }
            $this->error("Restore-test error: {$msg}{$hint}");
            report($e);

            return self::FAILURE;
        }
    }
}
