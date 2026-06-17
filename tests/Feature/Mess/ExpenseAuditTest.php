<?php

namespace Tests\Feature\Mess;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Services\ExpenseService;
use App\Support\ExpenseKind;
use Database\Seeders\ExpenseCategorySeeder;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseAuditTest extends TestCase
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

    public function test_creating_expense_writes_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $messId = Mess::activeId();
        $category = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->first();
        $member = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);

        $this->actingAs($admin);

        $service = app(ExpenseService::class);
        $service->create([
            'expense_category_id' => $category->id,
            'date' => now()->toDateString(),
            'purchased_by' => $member->id,
            'amount' => 500,
        ], null, ExpenseKind::BAZAR);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Expense::class,
            'event' => 'created',
        ]);
    }
}
