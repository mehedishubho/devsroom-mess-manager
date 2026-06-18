<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Locates the SQL dump inside an extracted spatie backup tree.
 *
 * spatie/laravel-backup Issue #1389 (Pitfall 1): the db-dumps/ folder may be
 * nested under the application source base path rather than sitting at the
 * extracted zip root. A recursive glob (STAR-STAR/db-dumps/STAR.sql) catches
 * both the flat legacy layout and the nested v8+ layout.
 */
class BackupPathResolver
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Locate the single SQL dump inside the extracted backup tree.
     *
     * PHP's native glob() does NOT walk subdirs for the `**` pattern, so we
     * use Symfony Finder to recursively locate any db-dumps/*.sql file. This
     * matches both the flat legacy layout (db-dumps/x.sql at the zip root)
     * and the nested Issue #1389 layout (storage/app/db-dumps/x.sql).
     *
     * @return string Absolute path to the .sql dump file.
     *
     * @throws RuntimeException When zero or more than one .sql dump is found.
     */
    public function locateSqlDump(string $extractedRoot): string
    {
        $finder = Finder::create()
            ->files()
            ->name('*.sql')
            ->in($extractedRoot)
            ->path('db-dumps');

        $matches = [];
        foreach ($finder as $file) {
            $matches[] = $file->getRealPath() ?: $file->getPathname();
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        throw new RuntimeException(
            count($matches) === 0
                ? 'No SQL dump found under db-dumps/ in the extracted backup.'
                : 'Multiple SQL dumps found in the backup; cannot auto-select.'
        );
    }
}
