<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RestoreTest;
use App\Support\BackupPathResolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * D-04 RestoreTestService — proves a backup actually restores.
 *
 * Loads the latest backup zip into the mysql_restore_test scratch DB (declared
 * byte-identical to the live mysql connection in Plan 06-01), then asserts
 * per-table COUNT(*) parity against the live DB. Restores use SELECT COUNT(*)
 * (NOT information_schema.TABLES.TABLE_ROWS — that is an InnoDB estimate).
 *
 * D-08: every Process + DB-restore invocation is mockable via protected seams
 * (downloadAndExtractLatest, wipeScratchDb, restoreDumpIntoScratch,
 * cleanupTempDir, countOnConnection). The test suite never shells out.
 */
class RestoreTestService
{
    /**
     * The explicit list of domain tables asserted for COUNT parity.
     *
     * Hardcoded rather than read from information_schema so the test is
     * deterministic across environments. Derived from database/migrations/.
     */
    public const DOMAIN_TABLES = [
        'users',
        'messes',
        'settings',
        'members',
        'meal_entries',
        'meal_off_requests',
        'guest_meals',
        'expense_categories',
        'expenses',
        'payments',
        'monthly_closings',
        'monthly_member_summaries',
        'monthly_corrections',
        'advance_balances',
        'notifications',
        'member_invitations',
        'audits',
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly BackupPathResolver $resolver,
    ) {}

    /**
     * Pure-logic comparison: for each table in $tables, COUNT(*) on the live
     * mysql connection vs. the mysql_restore_test scratch connection.
     *
     * @param  array<string>  $tables
     * @return array<int, array{table: string, live: int, test: int, pass: bool}>
     */
    public function compareCounts(array $tables = self::DOMAIN_TABLES): array
    {
        $results = [];
        foreach ($tables as $table) {
            $live = $this->countOnConnection(config('database.default'), $table);
            $test = $this->countOnConnection('mysql_restore_test', $table);
            $results[] = [
                'table' => $table,
                'live' => $live,
                'test' => $test,
                'pass' => $live === $test,
            ];
        }

        return $results;
    }

    /**
     * Full restore-test pipeline. Persists a RestoreTest row.
     */
    public function runLatest(): RestoreTest
    {
        $ranAt = now();
        $tempDir = null;

        try {
            $tempDir = $this->downloadAndExtractLatest();
            $sqlPath = $this->locateSqlDump($tempDir);

            // Pitfall 9: wipe the scratch DB before loading so it can't grow
            // monotonically across runs (and so contamination is impossible).
            $this->wipeScratchDb();
            $this->restoreDumpIntoScratch($sqlPath);

            $counts = $this->compareCounts();
            $allPass = collect($counts)->every(fn ($row) => $row['pass']);
            $divergent = collect($counts)->filter(fn ($row) => ! $row['pass'])->values();

            return RestoreTest::create([
                'status' => $allPass ? 'passed' : 'failed',
                'per_table_counts' => $counts,
                'message' => $allPass
                    ? null
                    : 'Per-table count divergence on: '
                        .collect($divergent)->pluck('table')->implode(', '),
                'ran_at' => $ranAt,
            ]);
        } catch (\Throwable $e) {
            // Persist the error so the UI badge surfaces it.
            return RestoreTest::create([
                'status' => 'error',
                'per_table_counts' => null,
                'message' => $e->getMessage(),
                'ran_at' => $ranAt,
            ]);
        } finally {
            if ($tempDir !== null) {
                $this->cleanupTempDir($tempDir);
            }
        }
    }

    /**
     * Resolve the .sql dump inside the extracted backup tree. Protected seam
     * (D-08) — the suite stubs this so the resolver's filesystem walk is
     * bypassed when runLatest()'s other protected seams are mocked.
     */
    protected function locateSqlDump(string $tempDir): string
    {
        return $this->resolver->locateSqlDump($tempDir);
    }

    /**
     * Count rows in $table on the given connection. Protected test seam so
     * the suite can stub this without touching a real DB.
     */
    protected function countOnConnection(string $connection, string $table): int
    {
        return (int) DB::connection($connection)->table($table)->count();
    }

    /**
     * Download the latest backup zip from the configured backups disk into a
     * fresh temp dir, then extract it. Protected seam (D-08).
     *
     * @return string The extracted work directory absolute path.
     */
    protected function downloadAndExtractLatest(): string
    {
        $diskName = config('backup.backup.destination.disks.0', 'backups');
        $disk = Storage::disk($diskName);

        $latestZip = collect($disk->allFiles())
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->sortByDesc(fn ($p) => $disk->lastModified($p))
            ->first();

        if ($latestZip === null) {
            throw new \RuntimeException('No backup zip found on the backups disk.');
        }

        $tempDir = storage_path('app/backup-temp/restore-test-'.time());
        $this->files->ensureDirectoryExists($tempDir, 0775, true);

        $localZip = $tempDir.'/'.basename($latestZip);
        // WR-01: stream the zip to disk instead of $disk->get() (which buffers
        // the whole file in memory → OOM on a real prod backup).
        $in = $disk->readStream($latestZip);
        $out = fopen($localZip, 'w+b');
        if ($out === false) {
            throw new \RuntimeException("Could not open {$localZip} for writing.");
        }
        stream_copy_to_stream($in, $out);
        fclose($out);
        if (is_resource($in)) {
            fclose($in);
        }

        $zip = new \ZipArchive;
        $opened = $zip->open($localZip);
        if ($opened !== true) {
            throw new \RuntimeException("Failed to open backup zip (code {$opened}).");
        }
        $zip->extractTo($tempDir);
        $zip->close();

        return $tempDir;
    }

    /**
     * Pitfall 9: wipe the scratch DB so the restore-test cannot grow or
     * contaminate across runs. Drops every table with FK checks disabled,
     * then re-enables. Protected seam (D-08).
     */
    protected function wipeScratchDb(): void
    {
        $conn = DB::connection('mysql_restore_test');
        $conn->statement('SET FOREIGN_KEY_CHECKS=0');
        $tables = $conn->getDoctrineSchemaManager()->listTableNames();
        foreach ($tables as $name) {
            $conn->statement("DROP TABLE IF EXISTS `{$name}`");
        }
        $conn->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Restore the SQL dump into the scratch DB via the mysql CLI wrapped in
     * Symfony Process (array args). Protected seam (D-08) — the suite mocks
     * this so no real subprocess ever runs.
     */
    protected function restoreDumpIntoScratch(string $sqlPath): void
    {
        $cfg = config('database.connections.mysql_restore_test');
        $process = new Process([
            'mysql',
            '--host='.$cfg['host'],
            '--port='.$cfg['port'],
            '--user='.$cfg['username'],
            '--password='.$cfg['password'],
            $cfg['database'],
        ]);
        // WR-03: pipe the dump via STDIN (avoids the `SOURCE <path>` form, which
        // breaks on paths containing spaces). A missing dump throws rather than
        // silently producing an empty restore.
        $resource = fopen($sqlPath, 'r');
        if ($resource === false) {
            throw new \RuntimeException("Could not open SQL dump for restore-test: {$sqlPath}");
        }
        $process->setInput($resource);
        $process->setTimeout(600);
        $process->mustRun();
    }

    protected function cleanupTempDir(string $tempDir): void
    {
        $this->files->deleteDirectory($tempDir);
    }
}
