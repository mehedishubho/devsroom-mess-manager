<?php

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\User;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyReportTest extends TestCase
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
        $admin->assignRole(Role::where('slug', 'admin')->first());

        return $admin;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('mess.reports.monthly'))
            ->assertRedirect('/login');
    }

    public function test_member_role_forbidden(): void
    {
        $this->actingAs($this->member())
            ->get(route('mess.reports.monthly'))
            ->assertForbidden();
    }

    public function test_manager_sees_totals_and_member_table(): void
    {
        $messId = Mess::activeId();
        $year = now()->year;
        $month = now()->month;
        $date = Carbon::create($year, $month, 1)->toDateString();

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Rahim Uddin',
        ]);
        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
        ]);
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'date' => $date,
            'amount' => 3000,
        ]);
        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $date,
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.monthly', ['year' => $year, 'month' => $month]));

        $response->assertOk();
        $response->assertSee(__('Monthly Report'));
        $response->assertSee(__('Meal rate'));
        $response->assertSee('Rahim Uddin');
        // Total bazar of 3000 should appear formatted via Money::taka
        $response->assertSee('৳3,000.00');
    }

    public function test_month_picker_query_param_changes_period(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.monthly', ['year' => 2026, 'month' => 5]));

        $response->assertOk();
        $response->assertSee('May 2026');
    }

    public function test_closed_month_uses_snapshot(): void
    {
        $messId = Mess::activeId();
        $admin = $this->admin();

        $closing = MonthlyClosing::factory()->create([
            'mess_id' => $messId,
            'year' => 2026,
            'month' => 4,
            'closed_by' => $admin->id,
            'total_bazar' => 12345.00,
            'total_fixed_expense' => 5000.00,
            'total_meals' => 100.00,
            'meal_rate' => 123.45,
            'member_count' => 1,
        ]);

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Snapshot Member',
        ]);

        MonthlyMemberSummary::create([
            'mess_id' => $messId,
            'monthly_closing_id' => $closing->id,
            'member_id' => $member->id,
            'total_meals' => 100.00,
            'meal_rate' => 123.4500,
            'meal_cost' => 12345.00,
            'fixed_cost_share' => 5000.00,
            'guest_meal_charge' => 0.00,
            'gross_bill' => 17345.00,
            'advance_applied' => 5000.00,
            'net_bill' => 12345.00,
            'payments_received' => 5000.00,
            'balance_due' => 12345.00,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('mess.reports.monthly', ['year' => 2026, 'month' => 4]));

        $response->assertOk();
        $response->assertSee('April 2026');
        $response->assertSee(__('Closed month'));
        $response->assertSee('Snapshot Member');
        // The snapshot's bazar total should appear, not a re-computed one.
        $response->assertSee('৳12,345.00');
    }

    public function test_empty_period_shows_hint_not_zero_wall(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.monthly', ['year' => 2024, 'month' => 1]));

        $response->assertOk();
        $response->assertSee(__('No data for :month yet', ['month' => 'January 2024']));
    }
}
