<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BackupConfig;

/**
 * The backup rotation decision, in ONE place.
 *
 * `backup:purge` enforces retention and the Backups page previews it. Those
 * used to be two independent implementations of the same rule — which is
 * exactly how a "what will be deleted" preview ends up lying about what the
 * next run actually deletes. This is a pure function over a file list, so the
 * two can never disagree.
 */
class BackupRetention
{
    /**
     * Decide what the next rotation removes.
     *
     * 1. Anything older than the keep window goes.
     * 2. If the survivors still exceed the storage cap, delete them
     *    oldest-first until under it.
     *
     * @param  array<int, array{path:string, size:int, ts:int}>  $files
     * @return array{delete: array<int, array{path:string, size:int, ts:int}>, keep: array<int, array{path:string, size:int, ts:int}>, delete_bytes: int}
     */
    public function plan(array $files, int $keepDays, int $maxBytes): array
    {
        $cutoff = now()->subDays(max(1, $keepDays))->getTimestamp();

        $delete = [];
        $survivors = [];

        foreach ($files as $file) {
            if ($file['ts'] < $cutoff) {
                $delete[] = $file;
            } else {
                $survivors[] = $file;
            }
        }

        // Delete oldest-first from the survivors until the cap is satisfied.
        usort($survivors, fn ($a, $b) => $a['ts'] <=> $b['ts']);

        $keep = $survivors;
        $total = array_sum(array_column($survivors, 'size'));

        while ($total > $maxBytes && $keep !== []) {
            $oldest = array_shift($keep);
            $delete[] = $oldest;
            $total -= $oldest['size'];
        }

        return [
            'delete' => $delete,
            'keep' => $keep,
            'delete_bytes' => (int) array_sum(array_column($delete, 'size')),
        ];
    }

    /** The configured retention window / cap. */
    public function config(): array
    {
        $config = BackupConfig::current();

        return [
            'keepDays' => max(1, (int) $config->keep_all_days),
            'maxBytes' => max(1, (int) $config->max_mb) * 1024 * 1024,
        ];
    }

    /** Convenience: plan against the current configuration. */
    public function planFor(array $files): array
    {
        $config = $this->config();

        return $this->plan($files, $config['keepDays'], $config['maxBytes']);
    }
}
