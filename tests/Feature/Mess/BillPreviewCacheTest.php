<?php

namespace Tests\Feature\Mess;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Services\BillPreviewService;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BillPreviewCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Cache::flush();
    }

    public function test_cache_hits_are_returned_for_same_month(): void
    {
        $service = app(BillPreviewService::class);
        $year = now()->year;
        $month = now()->month;

        $first = $service->preview($year, $month);
        $second = $service->preview($year, $month);

        $this->assertSame($first, $second);
    }

    public function test_payment_creation_invalidates_cache(): void
    {
        $service = app(BillPreviewService::class);
        $year = now()->year;
        $month = now()->month;

        $first = $service->preview($year, $month);

        Payment::factory()->create([
            'type' => PaymentType::ADVANCE_DEPOSIT,
            'method' => PaymentMethod::CASH,
            'date' => now()->toDateString(),
        ]);

        $second = $service->preview($year, $month);

        $this->assertNotSame($first, $second);
    }

    /**
     * CR-01 regression: a write dated in month M must clear month M's preview
     * cache (the listener must receive the model directly, not $event->model).
     */
    public function test_backdated_write_clears_only_the_affected_month_cache(): void
    {
        $service = app(BillPreviewService::class);
        $messId = Mess::activeId();

        // Warm both June and July caches.
        $service->preview(2026, 6);
        $service->preview(2026, 7);
        $juneKey = $service->cacheKey($messId, 2026, 6);
        $julyKey = $service->cacheKey($messId, 2026, 7);
        $this->assertTrue(Cache::has($juneKey));
        $this->assertTrue(Cache::has($julyKey));

        // A backdated MealEntry in June must clear June's cache only.
        $member = Member::factory()->create([
            'status' => MemberStatus::ACTIVE,
            'joining_date' => '2026-01-01',
        ]);
        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => '2026-06-15',
            'breakfast' => false,
            'lunch' => true,
            'dinner' => false,
        ]);

        $this->assertFalse(Cache::has($juneKey), 'June cache must be invalidated by a June-dated write');
        $this->assertTrue(Cache::has($julyKey), 'July cache must NOT be invalidated by a June-dated write');
    }

    /**
     * CR-01 regression: Expense writes invalidate the affected month's cache.
     */
    public function test_expense_write_invalidates_affected_month_cache(): void
    {
        $service = app(BillPreviewService::class);
        $messId = Mess::activeId();

        $service->preview(2026, 5);
        $mayKey = $service->cacheKey($messId, 2026, 5);
        $this->assertTrue(Cache::has($mayKey));

        $bazar = ExpenseCategory::factory()->create(['kind' => ExpenseKind::BAZAR]);
        Expense::factory()->create([
            'expense_category_id' => $bazar->id,
            'amount' => 250,
            'date' => '2026-05-10',
        ]);

        $this->assertFalse(Cache::has($mayKey));
    }

    /**
     * CR-01 / WR-02 regression: MealOffRequest uses `from_date` (not `date`) —
     * the resolver must read `from_date` so the affected month is invalidated.
     */
    public function test_meal_off_request_invalidates_affected_month_via_from_date(): void
    {
        $service = app(BillPreviewService::class);
        $messId = Mess::activeId();

        // Warm April (the from_date month) and September (an unrelated month).
        $service->preview(2026, 4);
        $service->preview(2026, 9);
        $aprilKey = $service->cacheKey($messId, 2026, 4);
        $septemberKey = $service->cacheKey($messId, 2026, 9);
        $this->assertTrue(Cache::has($aprilKey));
        $this->assertTrue(Cache::has($septemberKey));

        $member = Member::factory()->create([
            'status' => MemberStatus::ACTIVE,
            'joining_date' => '2026-01-01',
        ]);
        MealOffRequest::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'from_date' => '2026-04-12',
            'to_date' => '2026-04-14',
            'reason' => 'away',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->assertFalse(Cache::has($aprilKey), 'from_date month must be invalidated');
        $this->assertTrue(Cache::has($septemberKey), 'unrelated month must remain cached');
    }
}
