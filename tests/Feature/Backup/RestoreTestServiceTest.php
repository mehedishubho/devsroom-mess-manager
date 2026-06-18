<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\RestoreTest;
use App\Services\RestoreTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Plan 06-02 Task 1 — RestoreTestService (D-04 / D-08a).
 *
 * The comparison logic is the unit-test target. No real mysql/mysqldump ever
 * runs — DB::connection('mysql_restore_test') COUNT(*) queries are wired to a
 * real scratch DB connection (or a canned mock when the scratch DB is absent).
 */
class RestoreTestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 1: compareCounts() returns all-pass=true when every table's live
     * count equals its test count.
     */
    public function test_compare_counts_passes_when_all_tables_match(): void
    {
        $service = $this->makeServiceWithCannedCounts([
            'members' => ['live' => 5, 'test' => 5],
            'expenses' => ['live' => 8, 'test' => 8],
        ]);

        $results = $service->compareCounts(['members', 'expenses']);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['pass']);
        $this->assertTrue($results[1]['pass']);
    }

    /**
     * Test 2: compareCounts() returns at least one pass=false when one table's
     * live count differs from its test count.
     */
    public function test_compare_counts_fails_when_any_table_diverges(): void
    {
        $service = $this->makeServiceWithCannedCounts([
            'members' => ['live' => 5, 'test' => 5],
            'expenses' => ['live' => 8, 'test' => 7], // divergence
        ]);

        $results = $service->compareCounts(['members', 'expenses']);

        $this->assertTrue($results[0]['pass']); // members match
        $this->assertFalse($results[1]['pass']); // expenses diverge
        $this->assertSame(8, $results[1]['live']);
        $this->assertSame(7, $results[1]['test']);
    }

    /**
     * Test 3: runLatest() persists a 'passed' restore_tests row when every
     * table's counts match.
     *
     * The dump download + extract + load path is mocked; only compareCounts()
     * runs for real against a canned count map.
     */
    public function test_run_latest_persists_passed_row_when_counts_match(): void
    {
        $service = $this->makeServiceForRunLatest([
            'members' => ['live' => 5, 'test' => 5],
        ]);

        $result = $service->runLatest();

        $this->assertSame('passed', $result->status);
        $this->assertNotNull(RestoreTest::where('status', 'passed')->first());
        $this->assertNotNull($result->ran_at);
    }

    /**
     * Test 4: runLatest() persists a 'failed' restore_tests row with a message
     * when any count diverges.
     */
    public function test_run_latest_persists_failed_row_when_counts_diverge(): void
    {
        $service = $this->makeServiceForRunLatest([
            'members' => ['live' => 5, 'test' => 4], // divergence
        ]);

        $result = $service->runLatest();

        $this->assertSame('failed', $result->status);
        $this->assertNotEmpty($result->message);
        $this->assertNotNull(RestoreTest::where('status', 'failed')->first());
    }

    // --------------------------------------------------------------
    // Helpers
    // --------------------------------------------------------------

    /**
     * Build a RestoreTestService where the per-table COUNT(*) lookups are
     * canned — no real DB query. The compareCounts() body still executes
     * for real; only the COUNT fetcher seam is stubbed.
     *
     * @param  array<string, array{live: int, test: int}>  $counts
     */
    private function makeServiceWithCannedCounts(array $counts): RestoreTestService&MockInterface
    {
        $mock = Mockery::mock(RestoreTestService::class)->makePartial();
        // Required to mock the protected countOnConnection / downloadAndExtractLatest
        // / wipeScratchDb / restoreDumpIntoScratch / cleanupTempDir seams per D-08.
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('countOnConnection')
            ->andReturnUsing(function (string $connection, string $table) use ($counts) {
                $key = ($connection === 'mysql_restore_test') ? 'test' : 'live';

                return $counts[$table][$key] ?? 0;
            });

        return $mock;
    }

    /**
     * Same as above, plus stubs the dump load path so runLatest() can complete
     * without touching a real scratch DB.
     *
     * @param  array<string, array{live: int, test: int}>  $counts
     */
    private function makeServiceForRunLatest(array $counts): RestoreTestService&MockInterface
    {
        $mock = $this->makeServiceWithCannedCounts($counts);

        $mock->shouldReceive('downloadAndExtractLatest')->andReturn(sys_get_temp_dir().'/fake-restore-test');
        $mock->shouldReceive('locateSqlDump')->andReturn('/fake/dump.sql');
        $mock->shouldReceive('wipeScratchDb');
        $mock->shouldReceive('restoreDumpIntoScratch');
        $mock->shouldReceive('cleanupTempDir');

        return $mock;
    }
}
