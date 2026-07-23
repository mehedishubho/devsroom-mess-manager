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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task 2 of quick-260717-2q3 — editable expense categories.
 *
 * Covers: GET edit form (200/403), PATCH rename with slug regen, per-mess
 * slug-uniqueness (NOT global), default-category rename refusal, DELETE
 * refusal when expenses link to the category, role enforcement.
 */
class ExpenseCategoryEditTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        return $user;
    }

    public function test_admin_gets_edit_form_for_non_default_category(): void
    {
        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'kind' => ExpenseKind::BAZAR,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin())
            ->get(route('mess.categories.edit', $category))
            ->assertOk()
            ->assertSee('Edit category')
            ->assertSee('Vegetables');
    }

    public function test_admin_can_rename_category_and_slug_is_regenerated(): void
    {
        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Old Name',
            'slug' => 'old-name',
            'kind' => ExpenseKind::BAZAR,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin())
            ->from(route('mess.categories.edit', $category))
            ->patch(route('mess.categories.update', $category), [
                'name' => 'Fresh Vegetables',
            ])
            ->assertRedirect(route('mess.categories.index'));

        $category->refresh();
        $this->assertSame('Fresh Vegetables', $category->name);
        $this->assertSame('fresh-vegetables', $category->slug);
        $this->assertSame(Mess::activeId(), $category->mess_id);
    }

    public function test_rename_to_duplicate_slug_within_same_mess_is_rejected(): void
    {
        ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Existing',
            'slug' => 'existing',
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Other Cat',
            'slug' => 'other-cat',
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin())
            ->from(route('mess.categories.edit', $category))
            ->patch(route('mess.categories.update', $category), [
                'name' => 'Existing',
            ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_rename_to_duplicate_slug_in_different_mess_is_allowed(): void
    {
        $otherMess = Mess::factory()->create();
        ExpenseCategory::create([
            'mess_id' => $otherMess->id,
            'name' => 'Shared Name',
            'slug' => Str::slug('Shared Name'),
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'My Cat',
            'slug' => 'my-cat',
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin())
            ->from(route('mess.categories.edit', $category))
            ->patch(route('mess.categories.update', $category), [
                'name' => 'Shared Name',
            ])
            ->assertRedirect(route('mess.categories.index'));

        $category->refresh();
        $this->assertSame('Shared Name', $category->name);
    }

    public function test_default_category_cannot_be_renamed_via_edit_route(): void
    {
        $default = ExpenseCategory::where('is_default', true)->first();

        $this->actingAs($this->admin())
            ->get(route('mess.categories.edit', $default))
            ->assertForbidden();
    }

    public function test_default_category_cannot_be_renamed_via_update_route(): void
    {
        $default = ExpenseCategory::where('is_default', true)->first();
        $originalName = $default->name;

        $this->actingAs($this->admin())
            ->from(route('mess.categories.index'))
            ->patch(route('mess.categories.update', $default), [
                'name' => 'Hacked Default',
            ])
            ->assertSessionHasErrors(['name']);

        $default->refresh();
        $this->assertSame($originalName, $default->name);
    }

    public function test_delete_refused_when_category_has_expenses(): void
    {
        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Linked',
            'slug' => 'linked',
            'kind' => ExpenseKind::BAZAR,
            'is_default' => false,
        ]);

        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => 'active',
        ]);

        Expense::create([
            'mess_id' => Mess::activeId(),
            'expense_category_id' => $category->id,
            'date' => now()->toDateString(),
            'purchased_by' => $member->id,
            'amount' => 100,
            'entered_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($this->admin())
            ->from(route('mess.categories.index'))
            ->delete(route('mess.categories.destroy', $category))
            ->assertRedirect(route('mess.categories.index'))
            ->assertSessionHas('error');

        $this->assertNotNull($category->fresh());
    }

    public function test_delete_succeeds_for_non_default_category_without_expenses(): void
    {
        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Lonely',
            'slug' => 'lonely',
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('mess.categories.destroy', $category))
            ->assertRedirect(route('mess.categories.index'))
            ->assertSessionHas('success');

        $this->assertNull($category->fresh());
    }

    public function test_member_role_forbidden_on_edit_update_routes(): void
    {
        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Member Test',
            'slug' => 'member-test',
            'kind' => ExpenseKind::OTHER,
            'is_default' => false,
        ]);

        $this->actingAs($this->member())
            ->get(route('mess.categories.edit', $category))
            ->assertForbidden();

        $this->actingAs($this->member())
            ->patch(route('mess.categories.update', $category), ['name' => 'Hacked'])
            ->assertForbidden();
    }
}
