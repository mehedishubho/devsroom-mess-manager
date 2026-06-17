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
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportTest extends TestCase
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

    private function memberUser(Member $member): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $member->update(['user_id' => $user->id]);

        return $user;
    }

    private function seedMonthlyData(): void
    {
        $messId = Mess::activeId();
        $date = now()->toDateString();

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'PDF Test Member',
        ]);
        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
        ]);
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'date' => $date,
            'amount' => 2000,
        ]);
        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $date,
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ]);
        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $date,
            'amount' => 500,
        ]);
    }

    public function test_monthly_pdf_downloads(): void
    {
        $this->seedMonthlyData();
        $year = now()->year;
        $month = now()->month;

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.monthly.pdf', ['year' => $year, 'month' => $month]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $response->assertHeader('Content-Disposition', 'attachment; filename="monthly-report-'.$year.'-'.$monthStr.'.pdf"');
    }

    public function test_member_statement_pdf_downloads(): void
    {
        $this->seedMonthlyData();
        $member = Member::firstWhere('name', 'PDF Test Member');

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement.pdf', [
                'member_id' => $member->id,
                'year' => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_expenses_pdf_downloads(): void
    {
        $this->seedMonthlyData();

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.expenses.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_payments_pdf_downloads(): void
    {
        $this->seedMonthlyData();

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_member_own_statement_pdf(): void
    {
        $this->seedMonthlyData();
        $member = Member::firstWhere('name', 'PDF Test Member');
        $user = $this->memberUser($member);

        $response = $this->actingAs($user)
            ->get(route('my.reports.statement.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_member_aggregates_monthly_pdf(): void
    {
        $this->seedMonthlyData();
        $member = Member::firstWhere('name', 'PDF Test Member');
        $user = $this->memberUser($member);

        $response = $this->actingAs($user)
            ->get(route('my.reports.monthly.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_member_role_forbidden_on_manager_exports(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());

        $this->actingAs($user)
            ->get(route('mess.reports.monthly.pdf'))
            ->assertForbidden();
    }

    public function test_unauthenticated_redirected(): void
    {
        $this->get(route('mess.reports.monthly.pdf'))
            ->assertRedirect('/login');
    }

    public function test_cross_mess_member_pdf_returns_404(): void
    {
        // Foreign mess + foreign member
        $otherMess = Mess::factory()->create();
        $otherMember = Member::factory()->create([
            'mess_id' => $otherMess->id,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Foreign Member',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement.pdf', [
                'member_id' => $otherMember->id,
                'year' => now()->year,
                'month' => now()->month,
            ]));

        // MessScope auto-filters → Member::firstOrFail() → ModelNotFoundException → 404
        $response->assertNotFound();
    }
}
