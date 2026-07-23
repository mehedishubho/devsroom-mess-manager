<?php

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Mess;
use App\Models\User;
use App\Support\ExpenseKind;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseReportTest extends TestCase
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
        $this->get(route('mess.reports.expenses'))
            ->assertRedirect('/login');
    }

    public function test_member_role_forbidden(): void
    {
        $this->actingAs($this->member())
            ->get(route('mess.reports.expenses'))
            ->assertForbidden();
    }

    public function test_manager_sees_expenses_list(): void
    {
        $messId = Mess::activeId();
        $category = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
            'name' => 'Vegetables',
        ]);

        $expenses = [
            Expense::factory()->create([
                'mess_id' => $messId,
                'expense_category_id' => $category->id,
                'description' => 'Potatoes and onions',
                'amount' => 500,
                'date' => '2026-06-05',
            ]),
            Expense::factory()->create([
                'mess_id' => $messId,
                'expense_category_id' => $category->id,
                'description' => 'Rice sack',
                'amount' => 1500,
                'date' => '2026-06-10',
            ]),
            Expense::factory()->create([
                'mess_id' => $messId,
                'expense_category_id' => $category->id,
                'description' => 'Fish curry',
                'amount' => 800,
                'date' => '2026-06-15',
            ]),
        ];

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.expenses'));

        $response->assertOk();
        $response->assertSee(__('Expense Report'));
        $response->assertSee('Potatoes and onions');
        $response->assertSee('Rice sack');
        $response->assertSee('Fish curry');
        // Total 500 + 1500 + 800 = 2800 formatted via Money::taka
        $response->assertSee('৳2,800.00');
    }

    public function test_date_filter_sticky_in_url(): void
    {
        $messId = Mess::activeId();
        $category = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
        ]);

        // One expense inside the range, one outside
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $category->id,
            'description' => 'Inside range',
            'amount' => 100,
            'date' => '2026-06-10',
        ]);
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $category->id,
            'description' => 'Outside range',
            'amount' => 200,
            'date' => '2026-06-20',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.expenses', [
                'from' => '2026-06-01',
                'to' => '2026-06-15',
            ]));

        $response->assertOk();
        // Sticky: the filter inputs should carry the queried values
        $response->assertSee('value="2026-06-01"', false);
        $response->assertSee('value="2026-06-15"', false);
        // Only the in-range expense appears
        $response->assertSee('Inside range');
        $response->assertDontSee('Outside range');
    }

    public function test_category_filter(): void
    {
        $messId = Mess::activeId();
        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'name' => 'Bazar Cat',
            'kind' => ExpenseKind::BAZAR,
        ]);
        $fixed = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'name' => 'Fixed Cat',
            'kind' => ExpenseKind::FIXED,
        ]);

        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'description' => 'Bazar expense',
            'amount' => 100,
            'date' => now()->toDateString(),
        ]);
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $fixed->id,
            'description' => 'Fixed expense',
            'amount' => 200,
            'date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.expenses', ['category_id' => $bazar->id]));

        $response->assertOk();
        $response->assertSee('Bazar expense');
        $response->assertDontSee('Fixed expense');
    }

    public function test_this_month_preset_link_is_present(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.expenses'));

        $response->assertOk();
        $response->assertSee(__('This month'));
        $response->assertSee(__('Last month'));
        // The preset link URL should target the current month boundaries
        $response->assertSee(now()->startOfMonth()->toDateString());
        $response->assertSee(now()->endOfMonth()->toDateString());
    }

    public function test_empty_state_when_no_match(): void
    {
        $messId = Mess::activeId();
        $category = ExpenseCategory::factory()->create(['mess_id' => $messId, 'kind' => ExpenseKind::BAZAR]);
        // Expense exists, but outside the queried date range
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $category->id,
            'date' => '2025-01-15',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.expenses', [
                'from' => '2026-06-01',
                'to' => '2026-06-30',
            ]));

        $response->assertOk();
        $response->assertSee(__('No expenses match the current filters.'));
    }
}
