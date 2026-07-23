<?php

namespace Tests\Feature\Mess;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Services\BillPreviewService;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BillPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        Mess::forgetActiveIdCache();
        $mess = Mess::factory()->create();
        Mess::forgetActiveIdCache();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();
    }

    public function test_admin_can_view_bill_preview(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($admin)
            ->get(route('mess.bill-preview.index'))
            ->assertOk();
    }

    public function test_preview_computes_meal_rate(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);
        $messId = Mess::activeId();
        $year = (int) now()->year;
        $month = (int) now()->month;
        $start = Carbon::create($year, $month, 1)->toDateString();

        $bazar = ExpenseCategory::factory()->create(['kind' => ExpenseKind::BAZAR]);
        $expense = Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'date' => $start,
            'amount' => 3000,
        ]);

        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $start,
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ]);

        Cache::flush();
        $service = app(BillPreviewService::class);

        $this->assertGreaterThan(0, ExpenseCategory::query()->count(), 'ExpenseCategory should exist in scope');
        $this->assertGreaterThan(0, ExpenseCategory::query()->where('kind', ExpenseKind::BAZAR)->count(), 'Bazar category should exist in scope');

        $preview = $service->preview($year, $month);

        $this->assertGreaterThan(0, Expense::count(), 'Expense should exist');
        $this->assertSame($messId, $expense->mess_id, 'Expense mess_id mismatch');
        $this->assertSame($bazar->id, $expense->expense_category_id, 'Expense category mismatch');

        $bazarSum = Expense::query()
            ->where('mess_id', $messId)
            ->whereBetween('date', [$start, $start])
            ->whereIn('expense_category_id', [$bazar->id])
            ->sum('amount');
        $this->assertSame(3000.0, (float) $bazarSum, 'Direct sum mismatch');

        $this->assertSame(3000.0, (float) $preview['total_bazar']);
        $this->assertSame(2.5, (float) $preview['total_meals']);
        $this->assertSame(1200.0, (float) $preview['meal_rate']);
    }

    public function test_member_cannot_view_manager_bill_preview(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $this->actingAs($user)
            ->get(route('mess.bill-preview.index'))
            ->assertForbidden();
    }
}
