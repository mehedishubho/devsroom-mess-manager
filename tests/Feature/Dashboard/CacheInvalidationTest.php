<?php

namespace Tests\Feature\Dashboard;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    public function test_expense_write_forgets_dash_counts(): void
    {
        $messId = Mess::activeId();
        $now = Carbon::now();
        $key = "dash:counts:{$messId}:{$now->year}-".str_pad((string) $now->month, 2, '0', STR_PAD_LEFT);

        // Prime the cache by calling managerCards()
        app(DashboardService::class)->managerCards();
        $this->assertTrue(Cache::has($key));

        // Write an expense → should invalidate the cache
        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
        ]);
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'date' => $now->toDateString(),
            'amount' => 100,
            'entered_by' => $this->admin()->id,
        ]);

        $this->assertFalse(Cache::has($key));
    }

    public function test_payment_write_forgets_bill_preview_and_dash_counts(): void
    {
        $messId = Mess::activeId();
        $now = Carbon::now();
        $dashKey = "dash:counts:{$messId}:{$now->year}-".str_pad((string) $now->month, 2, '0', STR_PAD_LEFT);
        $billKey = "bill-preview:{$messId}:{$now->year}-".str_pad((string) $now->month, 2, '0', STR_PAD_LEFT);

        app(DashboardService::class)->managerCards();
        $this->assertTrue(Cache::has($dashKey));
        $this->assertTrue(Cache::has($billKey));

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
        ]);
        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $now->toDateString(),
            'amount' => 500,
            'entered_by' => $this->admin()->id,
        ]);

        $this->assertFalse(Cache::has($dashKey));
        $this->assertFalse(Cache::has($billKey));
    }

    public function test_dash_counts_key_is_mess_scoped(): void
    {
        $mess1 = Mess::activeId();

        // Create a second mess + its dash:counts key
        $mess2 = Mess::factory()->create();
        $now = Carbon::now();
        $key1 = "dash:counts:{$mess1}:{$now->year}-".str_pad((string) $now->month, 2, '0', STR_PAD_LEFT);
        $key2 = "dash:counts:{$mess2->id}:{$now->year}-".str_pad((string) $now->month, 2, '0', STR_PAD_LEFT);

        Cache::put($key1, ['x' => 1], now()->addHour());
        Cache::Put($key2, ['x' => 2], now()->addHour());

        // Write an expense in mess1 → should forget key1 but NOT key2
        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $mess1,
            'kind' => ExpenseKind::BAZAR,
        ]);
        Expense::factory()->create([
            'mess_id' => $mess1,
            'expense_category_id' => $bazar->id,
            'date' => $now->toDateString(),
            'amount' => 100,
            'entered_by' => $this->admin()->id,
        ]);

        $this->assertFalse(Cache::has($key1));
        $this->assertTrue(Cache::has($key2), 'dash:counts for mess2 must NOT be forgotten by a write in mess1');
    }
}
