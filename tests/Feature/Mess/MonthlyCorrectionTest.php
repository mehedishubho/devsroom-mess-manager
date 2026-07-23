<?php

namespace Tests\Feature\Mess;

use App\Models\AdvanceBalance;
use App\Models\Member;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\MonthlyCorrection;
use App\Models\MonthlyMemberSummary;
use App\Models\User;
use App\Support\MemberStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        return $admin;
    }

    public function test_positive_correction_credits_advance_balance(): void
    {
        $admin = $this->admin();
        $closing = MonthlyClosing::factory()->create(['mess_id' => Mess::activeId(), 'closed_by' => $admin->id]);
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $this->actingAs($admin)
            ->post(route('mess.closings.corrections.store', $closing), [
                'member_id' => $member->id,
                'amount' => 250,
                'reason' => 'Manual credit',
                'applied_to_year' => $closing->year,
                'applied_to_month' => $closing->month,
            ])
            ->assertRedirect(route('mess.closings.corrections.index', $closing));

        $ab = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertNotNull($ab);
        $this->assertSame(250.00, (float) $ab->balance);
        $this->assertSame(0.00, (float) $ab->due_balance);

        $this->assertDatabaseHas('monthly_corrections', [
            'member_id' => $member->id,
            'amount' => 250.00,
        ]);
    }

    public function test_negative_correction_increases_due_balance(): void
    {
        $admin = $this->admin();
        $closing = MonthlyClosing::factory()->create(['mess_id' => Mess::activeId(), 'closed_by' => $admin->id]);
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $this->actingAs($admin)
            ->post(route('mess.closings.corrections.store', $closing), [
                'member_id' => $member->id,
                'amount' => -100,
                'reason' => 'Manual due',
                'applied_to_year' => $closing->year,
                'applied_to_month' => $closing->month,
            ])
            ->assertRedirect(route('mess.closings.corrections.index', $closing));

        $ab = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertSame(100.00, (float) $ab->due_balance);
        $this->assertSame(0.00, (float) $ab->balance);
    }

    public function test_correction_does_not_mutate_existing_member_summary_snapshot(): void
    {
        $admin = $this->admin();
        $closing = MonthlyClosing::factory()->create(['mess_id' => Mess::activeId(), 'closed_by' => $admin->id]);
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);
        // Seed an immutable summary row that must not change when the correction is applied.
        $summary = MonthlyMemberSummary::create([
            'mess_id' => Mess::activeId(),
            'monthly_closing_id' => $closing->id,
            'member_id' => $member->id,
            'total_meals' => 60.00,
            'meal_rate' => 50.0000,
            'meal_cost' => 3000.00,
            'fixed_cost_share' => 0.00,
            'guest_meal_charge' => 0.00,
            'gross_bill' => 3000.00,
            'advance_applied' => 0.00,
            'net_bill' => 3000.00,
            'payments_received' => 0.00,
            'balance_due' => 3000.00,
        ]);

        $this->actingAs($admin)
            ->post(route('mess.closings.corrections.store', $closing), [
                'member_id' => $member->id,
                'amount' => -500,
                'reason' => 'Adjust after the fact',
                'applied_to_year' => $closing->year,
                'applied_to_month' => $closing->month,
            ]);

        $this->assertSame(3000.00, (float) $summary->fresh()->net_bill);
        $this->assertSame(3000.00, (float) $summary->fresh()->balance_due);
    }

    public function test_correction_writes_audit_log(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $closing = MonthlyClosing::factory()->create(['mess_id' => Mess::activeId(), 'closed_by' => $admin->id]);
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $this->post(route('mess.closings.corrections.store', $closing), [
            'member_id' => $member->id,
            'amount' => 100,
            'reason' => 'Audit me',
            'applied_to_year' => $closing->year,
            'applied_to_month' => $closing->month,
        ]);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => MonthlyCorrection::class,
        ]);
    }

    public function test_member_cannot_create_correction(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());
        $admin = $this->admin();
        $closing = MonthlyClosing::factory()->create(['mess_id' => Mess::activeId(), 'closed_by' => $admin->id]);
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $response = $this->actingAs($user)
            ->post(route('mess.closings.corrections.store', $closing), [
                'member_id' => $member->id,
                'amount' => 100,
                'reason' => 'Should not work',
                'applied_to_year' => $closing->year,
                'applied_to_month' => $closing->month,
            ]);

        $this->assertSame(403, $response->status());
        $this->assertDatabaseMissing('monthly_corrections', ['amount' => 100.00]);
    }
}
