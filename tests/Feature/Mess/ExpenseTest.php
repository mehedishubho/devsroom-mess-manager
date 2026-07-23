<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\ExpenseController;
use App\Http\Requests\Mess\StoreExpenseRequest;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\ExpenseKind;
use Database\Seeders\ExpenseCategorySeeder;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        $this->seed(ExpenseCategorySeeder::class);
    }

    public function test_admin_can_view_expenses_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($admin)
            ->get(route('mess.expenses.index'))
            ->assertOk()
            ->assertSee('Expenses');
    }

    public function test_admin_can_create_bazar_expense(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $messId = Mess::activeId();
        $category = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->first();
        $member = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);

        $controller = app(ExpenseController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreExpenseRequest::create(route('mess.expenses.store'), 'POST', [
            'expense_category_id' => $category->id,
            'date' => now()->toDateString(),
            'purchased_by' => $member->id,
            'vendor' => 'Karwan Bazaar',
            'description' => 'Daily groceries',
            'amount' => 1500.50,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $category->id,
            'purchased_by' => $member->id,
            'amount' => '1500.50',
        ]);
    }

    public function test_admin_can_create_fixed_expense(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $category = ExpenseCategory::where('kind', ExpenseKind::FIXED)->first();

        $controller = app(ExpenseController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreExpenseRequest::create(route('mess.expenses.store'), 'POST', [
            'expense_category_id' => $category->id,
            'date' => now()->toDateString(),
            'description' => 'November rent',
            'amount' => 12000,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $category->id,
            'amount' => '12000.00',
            'purchased_by' => null,
        ]);
    }
}
