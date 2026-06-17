<?php

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    /** @var Member|null Backing fields populated by seedData() per-test. */
    private ?Member $member = null;

    private ?ExpenseCategory $category = null;

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

    private function memberUser(Member $member): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $member->update(['user_id' => $user->id]);

        return $user;
    }

    private function seedData(): void
    {
        $messId = Mess::activeId();
        $date = now()->toDateString();

        $this->member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'XLSX Test Member',
        ]);
        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
        ]);
        $this->category = $bazar;
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'date' => $date,
            'amount' => 1500,
        ]);
        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $this->member->id,
            'date' => $date,
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ]);
        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $this->member->id,
            'date' => $date,
            'amount' => 750,
            'method' => 'cash',
        ]);
    }

    public function test_monthly_excel_downloads(): void
    {
        $this->seedData();

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.monthly.xlsx'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // Maatwebsite emits `filename=...` (no quotes). Assert on the
        // sanitized basename ending with .xlsx.
        $disposition = $response->headers->get('Content-Disposition', '');
        $this->assertStringContainsString(
            'monthly-report-',
            $disposition,
            'Content-Disposition must contain the sanitized monthly Excel filename'
        );
        $this->assertStringEndsWith('.xlsx', $disposition);
    }

    public function test_expenses_excel_with_filters(): void
    {
        $this->seedData();

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.expenses.xlsx', ['category_id' => $this->category->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_payments_excel_with_filters(): void
    {
        $this->seedData();

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments.xlsx', ['method' => 'cash']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_member_own_statement_excel(): void
    {
        $this->seedData();
        $user = $this->memberUser($this->member);

        $response = $this->actingAs($user)
            ->get(route('my.reports.statement.xlsx'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_member_aggregates_monthly_excel(): void
    {
        $this->seedData();
        $user = $this->memberUser($this->member);

        $response = $this->actingAs($user)
            ->get(route('my.reports.monthly.xlsx'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_filename_sanitized(): void
    {
        // Member name with path-traversal chars — filename must not contain them.
        $messId = Mess::activeId();
        $evil = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => '../evil/..',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement.xlsx', [
                'member_id' => $evil->id,
                'year' => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition', '');
        // No ".." or "/" may appear inside the filename="" portion
        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $m)) {
            $filename = $m[1];
            $this->assertStringNotContainsString('..', $filename, 'filename must not contain .. (path traversal)');
            $this->assertStringNotContainsString('/', $filename, 'filename must not contain / (path separator)');
            $this->assertStringNotContainsString('\\', $filename, 'filename must not contain \\ (path separator)');
        } else {
            $this->fail('Content-Disposition did not contain a filename: '.$disposition);
        }
    }
}
