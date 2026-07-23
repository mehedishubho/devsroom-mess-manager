<?php

namespace Tests\Feature\Mess;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Mess;
use App\Models\User;
use App\Support\ExpenseKind;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected int $messId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        $this->messId = $mess->id;
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::where('slug', 'admin')->first());
    }

    public function test_admin_can_view_expense_detail(): void
    {
        $category = ExpenseCategory::factory()->create(['kind' => ExpenseKind::BAZAR]);
        $expense = Expense::factory()->create([
            'mess_id' => $this->messId,
            'expense_category_id' => $category->id,
            'amount' => 1234.50,
            'vendor' => 'Rahim Store',
        ]);

        $this->actingAs($this->admin)
            ->get(route('mess.expenses.show', $expense))
            ->assertOk()
            ->assertSee('Rahim Store');
    }

    public function test_admin_can_open_edit_form(): void
    {
        $category = ExpenseCategory::factory()->create(['kind' => ExpenseKind::FIXED]);
        $expense = Expense::factory()->create([
            'mess_id' => $this->messId,
            'expense_category_id' => $category->id,
            'amount' => 500,
        ]);

        $this->actingAs($this->admin)
            ->get(route('mess.expenses.edit', $expense))
            ->assertOk()
            ->assertSee(__('Save changes'));
    }

    public function test_admin_can_update_expense(): void
    {
        $category = ExpenseCategory::factory()->create(['kind' => ExpenseKind::FIXED]);
        $expense = Expense::factory()->create([
            'mess_id' => $this->messId,
            'expense_category_id' => $category->id,
            'date' => now()->toDateString(),
            'amount' => 500,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('mess.expenses.update', $expense), [
                'expense_category_id' => $category->id,
                'date' => now()->toDateString(),
                'amount' => 999.99,
            ])
            ->assertRedirect(route('mess.expenses.index'));

        $this->assertSame('999.99', (string) $expense->fresh()->amount);
    }

    public function test_admin_can_delete_expense(): void
    {
        $category = ExpenseCategory::factory()->create(['kind' => ExpenseKind::FIXED]);
        $expense = Expense::factory()->create([
            'mess_id' => $this->messId,
            'expense_category_id' => $category->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('mess.expenses.destroy', $expense))
            ->assertRedirect(route('mess.expenses.index'));

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_expense_report_renders_view_action(): void
    {
        $category = ExpenseCategory::factory()->create(['kind' => ExpenseKind::BAZAR]);
        $expense = Expense::factory()->create([
            'mess_id' => $this->messId,
            'expense_category_id' => $category->id,
            'date' => now()->toDateString(),
            'amount' => 100,
        ]);

        $this->actingAs($this->admin)
            ->get(route('mess.reports.expenses'))
            ->assertOk()
            ->assertSee(__('View'))
            ->assertSee(route('mess.expenses.show', $expense));
    }
}
