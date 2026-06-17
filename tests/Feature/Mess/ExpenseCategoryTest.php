<?php

namespace Tests\Feature\Mess;

use App\Models\ExpenseCategory;
use App\Models\Mess;
use App\Models\User;
use App\Services\ExpenseCategoryService;
use App\Support\ExpenseKind;
use Database\Seeders\ExpenseCategorySeeder;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
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

    public function test_admin_can_view_categories(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->get(route('mess.categories.index'))
            ->assertOk()
            ->assertSee('Expense categories');
    }

    public function test_default_categories_are_seeded(): void
    {
        $this->assertGreaterThan(0, ExpenseCategory::where('is_default', true)->count());
        $this->assertTrue(ExpenseCategory::where('kind', ExpenseKind::BAZAR)->exists());
        $this->assertTrue(ExpenseCategory::where('kind', ExpenseKind::FIXED)->exists());
    }

    public function test_default_category_cannot_be_deleted(): void
    {
        $category = ExpenseCategory::where('is_default', true)->first();
        $service = app(ExpenseCategoryService::class);

        $this->assertFalse($service->delete($category));
        $this->assertNotNull($category->fresh());
    }

    public function test_custom_category_can_be_deleted(): void
    {
        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Custom',
            'slug' => 'custom',
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $service = app(ExpenseCategoryService::class);
        $this->assertTrue($service->delete($category));
        $this->assertNull($category->fresh());
    }
}
