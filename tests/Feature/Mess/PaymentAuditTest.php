<?php

namespace Tests\Feature\Mess;

use App\Models\AdvanceBalance;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\MemberStatus;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_creating_payment_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());
        $this->actingAs($user);

        $payment = Payment::factory()->create();

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'event' => 'created',
        ]);
    }

    /**
     * WR-01 regression: updating an ADVANCE_DEPOSIT payment must reverse the
     * original balance impact and apply the new one. Reducing 1000 -> 500 must
     * leave the balance at 500, not the stale 1000.
     */
    public function test_updating_advance_deposit_reverses_prior_balance_impact(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $this->actingAs($admin);
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(PaymentService::class);

        // Create an ADVANCE_DEPOSIT of 1000 — balance becomes 1000.
        $payment = $service->create([
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 1000,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::ADVANCE_DEPOSIT,
        ]);

        $ab = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertNotNull($ab);
        $this->assertSame('1000.00', (string) $ab->balance);

        // Edit the amount down to 500 — balance must end at 500, not 1000.
        $service->update($payment, [
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 500,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::ADVANCE_DEPOSIT,
        ]);

        $ab = $ab->fresh();
        $this->assertSame('500.00', (string) $ab->balance, 'Balance must reflect the new amount after update');
        $this->assertSame('0.00', (string) $ab->due_balance);
    }

    /**
     * WR-01 companion: BILL_PAYMENT updates must remain a no-op on balance
     * (matches applyPayment's no-op for BILL_PAYMENT).
     */
    public function test_updating_bill_payment_does_not_touch_advance_balance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $this->actingAs($admin);
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(PaymentService::class);

        // Seed a starting balance of 300 so we can detect any drift.
        AdvanceBalance::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'balance' => 300,
            'due_balance' => 0,
            'last_updated_at' => now(),
        ]);

        $payment = $service->create([
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 200,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
        ]);

        $service->update($payment, [
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 150,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
        ]);

        $ab = AdvanceBalance::where('member_id', $member->id)->first();
        $this->assertSame('300.00', (string) $ab->balance, 'BILL_PAYMENT updates must not affect balance');
    }
}
