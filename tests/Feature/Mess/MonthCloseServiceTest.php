<?php

namespace Tests\Feature\Mess;

use App\Models\AdvanceBalance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyMemberSummary;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdvanceBalanceService;
use App\Services\BillPreviewService;
use App\Services\MonthCloseService;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonthCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::where('slug', 'admin')->first());
    }

    /** Seed a single active member present for the whole month and return them. */
    private function seedFullMonthMember(string $joiningDate = '2026-01-01', int $year = 2026, int $month = 6, int $mealsPerDay = 2): Member
    {
        $member = Member::factory()->create([
            'status' => MemberStatus::ACTIVE,
            'joining_date' => $joiningDate,
        ]);
        $messId = Mess::activeId();
        $daysInMonth = (int) date('t', strtotime("$year-$month-01"));
        for ($d = 1; $d <= $daysInMonth; $d++) {
            MealEntry::factory()->create([
                'mess_id' => $messId,
                'member_id' => $member->id,
                'date' => sprintf('%d-%02d-%02d', $year, $month, $d),
                'breakfast' => false,
                'lunch' => $mealsPerDay >= 1,
                'dinner' => $mealsPerDay >= 2,
            ]);
        }

        return $member;
    }

    private function seedBazar(float $amount, int $year = 2026, int $month = 6, int $day = 10): void
    {
        $bazar = ExpenseCategory::factory()->create(['kind' => ExpenseKind::BAZAR]);
        Expense::factory()->create([
            'expense_category_id' => $bazar->id,
            'amount' => $amount,
            'date' => sprintf('%d-%02d-%02d', $year, $month, $day),
        ]);
    }

    private function seedFixed(float $amount, int $year = 2026, int $month = 6, int $day = 10): void
    {
        $fixed = ExpenseCategory::factory()->create(['kind' => ExpenseKind::FIXED]);
        Expense::factory()->create([
            'expense_category_id' => $fixed->id,
            'amount' => $amount,
            'date' => sprintf('%d-%02d-%02d', $year, $month, $day),
        ]);
    }

    public function test_idempotent_close_does_not_duplicate_rows(): void
    {
        $service = app(MonthCloseService::class);
        $first = $service->close(2026, 6, $this->admin->id);
        $second = $service->close(2026, 6, $this->admin->id);

        $this->assertTrue($first['was_recently_created']);
        $this->assertFalse($second['was_recently_created']);
        $this->assertSame($first['closing']->id, $second['closing']->id);
        $this->assertCount(1, MonthlyClosing::all());
    }

    public function test_meal_rate_is_total_bazar_over_total_meals(): void
    {
        // 60 meals (2/day x 30 days), bazar 6000 => rate 100
        $this->seedBazar(6000);
        $this->seedFixed(3000);
        $member = $this->seedFullMonthMember();

        $result = app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $this->assertSame(6000.0, (float) $result['closing']->total_bazar);
        $this->assertSame(3000.0, (float) $result['closing']->total_fixed_expense);
        $this->assertSame(100.0, (float) $result['closing']->meal_rate);
        $this->assertSame(60.0, (float) $result['closing']->total_meals);
        $this->assertSame(1, (int) $result['closing']->member_count);
        $this->assertCount(1, $result['summaries']);
    }

    public function test_mid_month_joiner_is_counted_in_meal_rate_denominator(): void
    {
        // A mid-month joiner (joining 2026-06-20) still ate 22 meals that consumed
        // groceries, so their meals count toward the meal-rate denominator. Rate =
        // bazar 3000 / 22 meals = 136.36. (Previously the joiner was excluded by
        // strict joining/leaving-date bounds, zeroing the rate — the bug behind
        // the ৳0.00 meal rate across reports + dashboard despite real data.)
        $this->seedBazar(3000);
        $late = Member::factory()->create([
            'status' => MemberStatus::ACTIVE,
            'joining_date' => '2026-06-20',
        ]);
        for ($d = 20; $d <= 30; $d++) {
            MealEntry::factory()->create([
                'mess_id' => Mess::activeId(),
                'member_id' => $late->id,
                'date' => sprintf('2026-06-%02d', $d),
                'breakfast' => false, 'lunch' => true, 'dinner' => true,
            ]);
        }

        $result = app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $this->assertSame(136.36, (float) $result['closing']->meal_rate);
        $this->assertSame(22.0, (float) $result['closing']->total_meals);
        $this->assertCount(1, $result['summaries']);
        $this->assertSame(22.0, (float) $result['summaries']->first()->total_meals);
    }

    public function test_zero_meal_member_is_included_in_summary_but_adds_no_meal_cost(): void
    {
        $this->seedBazar(3000);
        $eater = $this->seedFullMonthMember();   // 60 meals
        $faster = Member::factory()->create([
            'status' => MemberStatus::ACTIVE,
            'joining_date' => '2026-01-01',
        ]); // no meal entries

        $result = app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $this->assertCount(2, $result['summaries']);
        $fasterSummary = $result['summaries']->firstWhere('member_id', $faster->id);
        $this->assertSame(0.0, (float) $fasterSummary->meal_cost);
        // Rate is 3000/60 = 50
        $this->assertSame(50.0, (float) $result['closing']->meal_rate);
    }

    public function test_positive_net_bill_carries_to_due_balance(): void
    {
        // bazar 60 / 60 meals => rate 1; mealCost = 60*1 = 60; bill = 60; due = 60
        $this->seedBazar(60);
        $member = $this->seedFullMonthMember();

        app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $ab = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertNotNull($ab);
        $this->assertSame(60.0, (float) $ab->due_balance);
        $this->assertSame(0.0, (float) $ab->balance);
    }

    public function test_close_freezes_net_closing_balance_on_snapshot(): void
    {
        // bazar 60 / 60 meals => bill 60, unpaid => the member ends the month
        // owing 60, so the snapshot's closing_balance (signed net) must be -60.00.
        $this->seedBazar(60);
        $member = $this->seedFullMonthMember();

        $result = app(MonthCloseService::class)->close(2026, 6, $this->admin->id);
        $summary = $result['summaries']->firstWhere('member_id', $member->id);

        $this->assertSame('-60.00', (string) $summary->fresh()->closing_balance);
    }

    public function test_payment_received_is_subtracted_from_net_bill_in_snapshot(): void
    {
        // bazar 60 => rate 1 => mealCost 60 => bill 60; payment 20 => net 40
        $this->seedBazar(60);
        $member = $this->seedFullMonthMember();
        Payment::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => '2026-06-05',
            'amount' => 20,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
            'entered_by' => $this->admin->id,
        ]);

        $result = app(MonthCloseService::class)->close(2026, 6, $this->admin->id);
        $summary = $result['summaries']->firstWhere('member_id', $member->id);

        // payments_received 20; no advance deposit, so advance_applied 0.
        // net_bill = bill - payments - advance_applied = 60 - 20 - 0 = 40.
        $this->assertSame(20.0, (float) $summary->payments_received);
        $this->assertSame(0.0, (float) $summary->advance_applied);
        $this->assertSame(40.0, (float) $summary->net_bill);

        // Carry-forward: net_bill 40 positive => due_balance grows by 40
        $ab = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertSame(40.0, (float) $ab->due_balance);
    }

    public function test_close_numbers_match_bill_preview_service_for_same_inputs(): void
    {
        $this->seedBazar(4500);
        $this->seedFixed(1500);
        $this->seedFullMonthMember();

        $preview = app(BillPreviewService::class)->preview(2026, 6);
        $result = app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $this->assertSame($preview['total_bazar'], (float) $result['closing']->total_bazar);
        $this->assertSame($preview['total_fixed'], (float) $result['closing']->total_fixed_expense);
        $this->assertSame($preview['total_meals'], (float) $result['closing']->total_meals);
        $this->assertSame($preview['meal_rate'], (float) $result['closing']->meal_rate);
        $this->assertSame(count($preview['members']), (int) $result['closing']->member_count);

        $row = $preview['members'][0];
        $summary = $result['summaries']->firstWhere('member_id', $row['member_id']);
        $this->assertSame($row['meal_cost'], (float) $summary->meal_cost);
        $this->assertSame($row['fixed_share'], (float) $summary->fixed_cost_share);
        $this->assertSame($row['due'], (float) $summary->net_bill);
    }

    public function test_second_close_returns_same_closing_and_same_summaries(): void
    {
        $this->seedBazar(3000);
        $this->seedFullMonthMember();

        $service = app(MonthCloseService::class);
        $first = $service->close(2026, 6, $this->admin->id);
        $firstSummaryIds = $first['summaries']->pluck('id')->all();
        $firstSummaryCount = MonthlyMemberSummary::count();

        $second = $service->close(2026, 6, $this->admin->id);

        $this->assertFalse($second['was_recently_created']);
        $this->assertSame($firstSummaryIds, $second['summaries']->pluck('id')->all());
        $this->assertSame($firstSummaryCount, MonthlyMemberSummary::count());
    }

    public function test_member_summary_snapshot_stays_immutable_after_second_close(): void
    {
        $this->seedBazar(3000);
        $member = $this->seedFullMonthMember();

        $service = app(MonthCloseService::class);
        $service->close(2026, 6, $this->admin->id);
        $summaryBefore = MonthlyMemberSummary::where('member_id', $member->id)->first();
        $netBefore = (float) $summaryBefore->net_bill;

        // Re-close must NOT touch existing summaries
        $service->close(2026, 6, $this->admin->id);

        $summaryAfter = $summaryBefore->fresh();
        $this->assertSame($netBefore, (float) $summaryAfter->net_bill);
    }

    public function test_close_writes_close_complete_notification_for_managers(): void
    {
        $this->seedBazar(1000);
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        // The admin must belong to the active mess (WR-08: broadcastToManagers
        // now scopes admins by Member.mess_id == Mess::activeId()).
        Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'user_id' => $admin->id,
            'status' => MemberStatus::ACTIVE,
        ]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::where('slug', 'super-admin')->first());
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());

        app(MonthCloseService::class)->close(2026, 6, $admin->id);

        // Both managers get the notification, the regular member does not.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'close_complete',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'type' => 'close_complete',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
        ]);
    }

    public function test_mess_isolation_one_mess_close_does_not_affect_another(): void
    {
        // The active mess (from setUp) gets a closing for June 2026.
        $this->seedBazar(60);
        $this->seedFullMonthMember();
        app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $activeMessId = Mess::activeId();
        $this->assertSame(1, MonthlyClosing::where('mess_id', $activeMessId)->count());

        // A second, unrelated mess has no closing at all.
        $messB = Mess::factory()->create();
        $this->assertSame(0, MonthlyClosing::where('mess_id', $messB->id)->count());
    }

    public function test_close_invalidates_bill_preview_cache(): void
    {
        $this->seedBazar(3000);
        $this->seedFullMonthMember();

        $service = app(BillPreviewService::class);
        $service->preview(2026, 6); // warm cache
        $cacheKey = $service->cacheKey(Mess::activeId(), 2026, 6);
        $this->assertTrue(Cache::has($cacheKey));

        app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_consecutive_month_closes_accumulate_balance_in_bc_math(): void
    {
        // CR-03: closing two consecutive months must accumulate advance/due
        // balances in exact BC math on the SAME advance_balances row — never a
        // float round-trip. June's due and July's due (with cents) add exactly,
        // and a mid-stream advance deposit lands in the separate `balance` column.
        $member = $this->seedFullMonthMember('2026-01-01', 2026, 6, 2);

        // June: bazar 60 / 60 meals => rate 1 => bill 60 => net_bill 60 (due).
        $this->seedBazar(60, 2026, 6);
        app(MonthCloseService::class)->close(2026, 6, $this->admin->id);

        $afterJune = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertSame('60.00', (string) $afterJune->due_balance);
        $this->assertSame('0.00', (string) $afterJune->balance);

        // Between months: a 100 advance deposit → balance (D-07, applyPayment).
        $deposit = Payment::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => '2026-07-03',
            'amount' => 100,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::ADVANCE_DEPOSIT,
            'entered_by' => $this->admin->id,
        ]);
        app(AdvanceBalanceService::class)->applyPayment($deposit);

        // July: 31 days x 2 meals = 62 meals; bazar 62 => rate 1 => bill 62.
        // A 0.25 bill payment => net_bill 61.75 (due).
        $this->seedBazar(62, 2026, 7);
        for ($d = 1; $d <= 31; $d++) {
            MealEntry::factory()->create([
                'mess_id' => Mess::activeId(),
                'member_id' => $member->id,
                'date' => sprintf('2026-07-%02d', $d),
                'breakfast' => false, 'lunch' => true, 'dinner' => true,
            ]);
        }
        Payment::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => '2026-07-05',
            'amount' => 0.25,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
            'entered_by' => $this->admin->id,
        ]);

        app(MonthCloseService::class)->close(2026, 7, $this->admin->id);

        // End-state (advance now offsets the bill, D-07): July's bill of 62 with
        // 0.25 paid leaves 61.75 owed, covered by the 100 advance credit
        // (advanceApplied = 61.75, consumed at close). The leftover 38.25 credit
        // then nets against June's 60.00 due → 21.75 due, 0.00 credit. All exact
        // BC math across two closes on the same advance_balances row (CR-03).
        $final = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertSame('0.00', (string) $final->balance);
        $this->assertSame('21.75', (string) $final->due_balance);
    }
}
