<?php

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 4 of quick-260717-2q3 — data-driven month-picker range.
 *
 * Replaces the hardcoded 24-month window with min/max data span across
 * meal_entries, expenses, payments, monthly_closings for the active mess.
 */
class DataDrivenMonthRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_available_month_range_returns_last_12_months_when_no_data(): void
    {
        $service = app(ReportService::class);
        $range = $service->availableMonthRange(Mess::activeId());

        $expectedFirst = now()->copy()->subMonths(11)->startOfMonth();
        $expectedLast = now()->copy()->startOfMonth();

        $this->assertSame($expectedFirst->year, $range['first']['year']);
        $this->assertSame($expectedFirst->month, $range['first']['month']);
        $this->assertSame($expectedLast->year, $range['last']['year']);
        $this->assertSame($expectedLast->month, $range['last']['month']);
    }

    public function test_available_month_range_uses_earliest_expense_as_first(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);
        $category = ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => 'Groceries',
            'slug' => 'groceries',
            'kind' => ExpenseKind::BAZAR,
            'is_default' => false,
        ]);

        // Expense in March 2025 — earlier than any other data.
        Expense::create([
            'mess_id' => Mess::activeId(),
            'expense_category_id' => $category->id,
            'date' => '2025-03-15',
            'purchased_by' => $member->id,
            'amount' => 500,
            'entered_by' => User::factory()->create()->id,
        ]);

        $service = app(ReportService::class);
        $range = $service->availableMonthRange(Mess::activeId());

        $this->assertSame(2025, $range['first']['year']);
        $this->assertSame(3, $range['first']['month']);
    }

    public function test_available_month_range_uses_max_across_all_sources_for_first_bound(): void
    {
        // Latest data point is October 2025; 'first' should be the data-min.
        // (The 'last' upper bound is always the current month per the plan
        // example — the user may navigate to "this month" even without data.)
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);
        Payment::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => '2025-10-10',
            'amount' => 100,
            'method' => 'cash',
            'type' => 'bill_payment',
            'entered_by' => User::factory()->create()->id,
        ]);

        $service = app(ReportService::class);
        $range = $service->availableMonthRange(Mess::activeId());

        // First = earliest data point (Oct 2025 — only data row).
        $this->assertSame(2025, $range['first']['year']);
        $this->assertSame(10, $range['first']['month']);
        // Last is always now (current month) so navigation reaches "this month".
        $this->assertSame(now()->year, $range['last']['year']);
        $this->assertSame(now()->month, $range['last']['month']);
    }

    public function test_available_month_range_uses_monthly_closings_when_no_other_data(): void
    {
        // Use the factory so all NOT-NULL columns (member_count etc.) populate.
        MonthlyClosing::factory()->create([
            'mess_id' => Mess::activeId(),
            'year' => 2024,
            'month' => 11,
        ]);

        $service = app(ReportService::class);
        $range = $service->availableMonthRange(Mess::activeId());

        $this->assertSame(2024, $range['first']['year']);
        $this->assertSame(11, $range['first']['month']);
    }

    public function test_manager_monthly_report_dropdown_spans_only_data_range(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'name' => 'Alpha Member',
        ]);
        MealEntry::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => '2025-03-15',
            'breakfast' => true,
            'lunch' => false,
            'dinner' => false,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $response = $this->actingAs($admin)
            ->get(route('mess.reports.monthly', ['year' => 2025, 'month' => 3]));

        $response->assertOk();
        $html = $response->getContent();

        // Earliest month should appear.
        $this->assertStringContainsString('March 2025', $html);
        // The current month label should appear.
        $this->assertStringContainsString(now()->translatedFormat('F Y'), $html);
        // August 2024 (the old hardcoded 24-month window's earliest) must NOT.
        $this->assertStringNotContainsString('August 2024', $html);
    }

    public function test_month_nav_still_renders_prev_next_and_this_month_links(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $response = $this->actingAs($admin)
            ->get(route('mess.reports.monthly'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Previous month', $html);
        $this->assertStringContainsString('Next month', $html);
        $this->assertStringContainsString('This month', $html);
    }

    public function test_member_side_statement_route_uses_data_driven_range(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole(Role::where('slug', 'user')->first());

        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'user_id' => $user->id,
            'name' => 'Self Member',
        ]);

        MealEntry::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => '2025-06-10',
            'breakfast' => true,
            'lunch' => false,
            'dinner' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('my.reports.statement'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('June 2025', $html);
        $this->assertStringNotContainsString('August 2024', $html);
    }
}
