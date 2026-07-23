<?php

namespace Tests\Feature\Mess;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\ExpenseKind;
use Database\Seeders\ExpenseCategorySeeder;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Task 6 of quick-260717-2q3 — unified "Add expense" form.
 *
 * The single /mess/expenses/create form lists all categories grouped by
 * kind; the kind is inferred from the chosen category. Bazar-kind expenses
 * require purchased_by; fixed/other do not.
 */
class UnifiedExpenseCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        $this->seed(ExpenseCategorySeeder::class);
        Storage::fake('public');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    public function test_unified_create_form_renders_with_grouped_categories(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('mess.expenses.create'));
        $response->assertOk();
        $response->assertSee('Add expense');
        $response->assertSee('Save expense');

        // Optgroups for every kind in ExpenseKind::ALL.
        $html = $response->getContent();
        $this->assertStringContainsString('<optgroup label="Bazar">', $html);
        $this->assertStringContainsString('<optgroup label="Fixed">', $html);
    }

    public function test_store_with_bazar_category_creates_expense(): void
    {
        $category = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->first();
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => 'active',
        ]);

        $this->actingAs($this->admin())
            ->post(route('mess.expenses.store'), [
                'expense_category_id' => $category->id,
                'date' => now()->toDateString(),
                'purchased_by' => $member->id,
                'vendor' => 'Karwan Bazaar',
                'amount' => 250.75,
            ])
            ->assertRedirect(route('mess.expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $category->id,
            'purchased_by' => $member->id,
            'amount' => '250.75',
        ]);
    }

    public function test_store_with_fixed_category_creates_expense_without_purchased_by(): void
    {
        $category = ExpenseCategory::where('kind', ExpenseKind::FIXED)->first();

        $this->actingAs($this->admin())
            ->post(route('mess.expenses.store'), [
                'expense_category_id' => $category->id,
                'date' => now()->toDateString(),
                'description' => 'November rent',
                'amount' => 12000,
            ])
            ->assertRedirect(route('mess.expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $category->id,
            'purchased_by' => null,
            'amount' => '12000.00',
        ]);
    }

    public function test_store_with_cross_mess_category_id_is_rejected(): void
    {
        // Category from a different mess — MessScope excludes it from the
        // exists rule, so the request fails with 422 (session errors).
        $otherMess = Mess::factory()->create();
        $foreignCategory = ExpenseCategory::create([
            'mess_id' => $otherMess->id,
            'name' => 'Foreign Cat',
            'slug' => 'foreign-cat',
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin())
            ->post(route('mess.expenses.store'), [
                'expense_category_id' => $foreignCategory->id,
                'date' => now()->toDateString(),
                'amount' => 100,
            ])
            ->assertSessionHasErrors(['expense_category_id']);
    }

    public function test_bazar_category_requires_purchased_by(): void
    {
        $category = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->first();

        $this->actingAs($this->admin())
            ->post(route('mess.expenses.store'), [
                'expense_category_id' => $category->id,
                'date' => now()->toDateString(),
                'amount' => 500,
                // no purchased_by
            ])
            ->assertSessionHasErrors(['purchased_by']);
    }

    public function test_fixed_category_does_not_require_purchased_by(): void
    {
        $category = ExpenseCategory::where('kind', ExpenseKind::FIXED)->first();

        $this->actingAs($this->admin())
            ->post(route('mess.expenses.store'), [
                'expense_category_id' => $category->id,
                'date' => now()->toDateString(),
                'amount' => 500,
                // no purchased_by — should pass
            ])
            ->assertRedirect(route('mess.expenses.index'));
    }

    public function test_old_split_routes_are_removed(): void
    {
        // The locked decision: the old split routes are GONE. The unified
        // route is the only entry point.
        $this->assertFalse(Route::has('mess.expenses.bazar.create'));
        $this->assertFalse(Route::has('mess.expenses.fixed.create'));
        $this->assertFalse(Route::has('mess.expenses.bazar.store'));
        $this->assertFalse(Route::has('mess.expenses.fixed.store'));
    }

    public function test_expenses_index_shows_single_add_expense_button(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('mess.expenses.index'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Add expense', $html);
        $this->assertStringContainsString(route('mess.expenses.create'), $html);
    }

    public function test_receipt_upload_works_via_unified_form(): void
    {
        $category = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->first();
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => 'active',
        ]);
        $receipt = UploadedFile::fake()->image('receipt.jpg');

        $this->actingAs($this->admin())
            ->post(route('mess.expenses.store'), [
                'expense_category_id' => $category->id,
                'date' => now()->toDateString(),
                'purchased_by' => $member->id,
                'amount' => 300,
                'receipt' => $receipt,
            ])
            ->assertRedirect(route('mess.expenses.index'));

        $expense = Expense::latest('id')->first();
        $this->assertNotNull($expense->receipt_path);
        Storage::disk('public')->assertExists($expense->receipt_path);
    }
}
