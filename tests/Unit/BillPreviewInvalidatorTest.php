<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Mess;
use App\Services\BillPreviewInvalidator;
use App\Services\BillPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Plan 05-02 Task 3 — D-22 targeted gap-fill test for BillPreviewInvalidator.
 *
 * Coverage baseline before this file: BillPreviewInvalidator 54.55% (6/11).
 * The existing BillPreviewCacheTest exercises cache invalidation indirectly
 * via Eloquent saved/deleted events (AppServiceProvider::invalidateForModel),
 * but it does NOT exercise BillPreviewInvalidator::forDate() directly. That
 * method has 3 early-return guards + a Cache::forget success path:
 *
 *   - null/empty date → no-op (return early)
 *   - null active mess → no-op (return early)
 *   - un-parseable date → no-op (return early, swallow Throwable)
 *   - valid date → Cache::forget the bill-preview:{mess}:{Y}-{M} key
 *
 * This test drives all 4 branches. It locks the contract: a malformed date
 * MUST NOT throw (defensive guard), and a valid date MUST evict the cache
 * entry (otherwise the manager sees a stale bill preview — a real-world bug).
 */
class BillPreviewInvalidatorTest extends TestCase
{
    use RefreshDatabase;

    private BillPreviewInvalidator $invalidator;

    private BillPreviewService $preview;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invalidator = app(BillPreviewInvalidator::class);
        $this->preview = app(BillPreviewService::class);
    }

    public function test_for_date_does_nothing_when_date_is_null(): void
    {
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        // Populate the cache, then verify a null date call does NOT clear it.
        $this->preview->preview(2026, 6);
        $key = $this->preview->cacheKey($mess->id, 2026, 6);
        $this->assertTrue(Cache::has($key), 'cache should be populated before invalidation');

        $this->invalidator->forDate(null);

        $this->assertTrue(
            Cache::has($key),
            'null date must be a no-op — cache should still be present'
        );
    }

    public function test_for_date_does_nothing_when_date_is_empty_string(): void
    {
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        $this->preview->preview(2026, 6);
        $key = $this->preview->cacheKey($mess->id, 2026, 6);

        $this->invalidator->forDate('');

        $this->assertTrue(
            Cache::has($key),
            'empty-string date must be a no-op — cache should still be present'
        );
    }

    public function test_for_date_does_nothing_when_no_active_mess(): void
    {
        // No mess configured → Mess::activeId() returns null.
        config(['mess.active_mess_id' => null]);
        Mess::forgetActiveIdCache();

        // Must not throw and must not attempt Cache::forget with a null key.
        $this->invalidator->forDate('2026-06-18');

        $this->expectNotToPerformAssertions();
    }

    public function test_for_date_swallows_un_parseable_date_strings(): void
    {
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        $this->preview->preview(2026, 6);
        $key = $this->preview->cacheKey($mess->id, 2026, 6);

        // Garbage date string — Carbon::parse would throw without the guard.
        $this->invalidator->forDate('not-a-real-date');

        $this->assertTrue(
            Cache::has($key),
            'invalid date must be swallowed silently — cache should still be present'
        );
    }

    public function test_for_date_clears_cache_for_valid_date(): void
    {
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        $this->preview->preview(2026, 6);
        $key = $this->preview->cacheKey($mess->id, 2026, 6);
        $this->assertTrue(Cache::has($key), 'cache should be populated before invalidation');

        $this->invalidator->forDate('2026-06-18');

        $this->assertFalse(
            Cache::has($key),
            'valid date must evict the bill-preview:{mess}:{Y}-{M} cache entry'
        );
    }

    public function test_for_date_only_clears_the_targeted_month(): void
    {
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        // Populate two months.
        $this->preview->preview(2026, 5);
        $this->preview->preview(2026, 6);
        $mayKey = $this->preview->cacheKey($mess->id, 2026, 5);
        $juneKey = $this->preview->cacheKey($mess->id, 2026, 6);

        // Invalidate June only.
        $this->invalidator->forDate('2026-06-15');

        $this->assertFalse(Cache::has($juneKey), 'June entry should be evicted');
        $this->assertTrue(
            Cache::has($mayKey),
            'May entry should be untouched — invalidation is month-scoped'
        );
    }

    public function test_for_today_invalidates_current_month(): void
    {
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        $now = now();
        $this->preview->preview($now->year, $now->month);
        $key = $this->preview->cacheKey($mess->id, $now->year, $now->month);
        $this->assertTrue(Cache::has($key));

        $this->invalidator->forToday();

        $this->assertFalse(
            Cache::has($key),
            'forToday() must evict the current month cache entry'
        );
    }
}
