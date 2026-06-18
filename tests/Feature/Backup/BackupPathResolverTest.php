<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Support\BackupPathResolver;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * Plan 06-02 Task 1 — BackupPathResolver (Pitfall 1 mitigation).
 *
 * spatie/laravel-backup Issue #1389: the db-dumps folder may live at the
 * extracted tree root OR nested under a source base path. The resolver globs
 * the recursive pattern "STAR-STAR/db-dumps/STAR.sql" and refuses to auto-select
 * when zero or more than one sql file is found.
 */
class BackupPathResolverTest extends TestCase
{
    /**
     * Test 11: flat layout — db-dumps/foo.sql at the extracted root.
     */
    public function test_locate_sql_dump_returns_path_for_flat_layout(): void
    {
        $root = $this->makeTree([
            'db-dumps/live.sql' => 'SELECT 1;',
        ]);

        $resolver = new BackupPathResolver(new Filesystem());

        $this->assertSame(
            $root.'/db-dumps/live.sql',
            $resolver->locateSqlDump($root),
        );
    }

    /**
     * Test 12: nested Issue #1389 layout — db-dumps/ nested under a base path.
     */
    public function test_locate_sql_dump_returns_path_for_nested_layout(): void
    {
        $root = $this->makeTree([
            'storage/app/db-dumps/devsroom_mess.sql' => 'SELECT 1;',
        ]);

        $resolver = new BackupPathResolver(new Filesystem());

        $this->assertSame(
            $root.'/storage/app/db-dumps/devsroom_mess.sql',
            $resolver->locateSqlDump($root),
        );
    }

    public function test_locate_sql_dump_throws_when_no_dump_present(): void
    {
        $root = $this->makeTree([
            'storage/app/public/photo.jpg' => 'binary',
        ]);

        $resolver = new BackupPathResolver(new Filesystem());

        $this->expectException(\RuntimeException::class);
        $resolver->locateSqlDump($root);
    }

    public function test_locate_sql_dump_throws_when_multiple_dumps_present(): void
    {
        $root = $this->makeTree([
            'db-dumps/a.sql' => 'SELECT 1;',
            'nested/db-dumps/b.sql' => 'SELECT 2;',
        ]);

        $resolver = new BackupPathResolver(new Filesystem());

        $this->expectException(\RuntimeException::class);
        $resolver->locateSqlDump($root);
    }

    /**
     * Build a temp tree of files relative to a fresh root dir.
     *
     * @param  array<string, string>  $files  relative-path => contents
     */
    private function makeTree(array $files): string
    {
        $root = sys_get_temp_dir().'/backup-resolver-test-'.uniqid('', true);

        @mkdir($root, 0777, true);

        foreach ($files as $relative => $contents) {
            $full = $root.'/'.$relative;
            @mkdir(dirname($full), 0777, true);
            file_put_contents($full, $contents);
        }

        return $root;
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup of temp trees created in this test.
        $glob = sys_get_temp_dir().'/backup-resolver-test-*';
        foreach (glob($glob) ?: [] as $dir) {
            $this->removeTree($dir);
        }

        parent::tearDown();
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path.'/'.$entry);
        }

        @rmdir($path);
    }
}
