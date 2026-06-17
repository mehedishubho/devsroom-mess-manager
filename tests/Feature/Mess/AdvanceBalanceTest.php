<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\AdvanceBalanceController;
use App\Http\Requests\Mess\AdjustAdvanceBalanceRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdvanceBalanceService;
use App\Support\MemberStatus;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class AdvanceBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_advance_deposit_increases_advance_balance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(AdvanceBalanceService::class);
        $payment = Payment::factory()->advanceDeposit()->create([
            'member_id' => $member->id,
            'amount' => 1500,
            'method' => PaymentMethod::CASH,
        ]);

        $service->applyPayment($payment);

        $this->assertDatabaseHas('advance_balances', [
            'member_id' => $member->id,
            'balance' => '1500.00',
            'due_balance' => '0.00',
        ]);
    }

    public function test_bill_payment_does_not_touch_advance_balance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(AdvanceBalanceService::class);
        $payment = Payment::factory()->create([
            'member_id' => $member->id,
            'type' => PaymentType::BILL_PAYMENT,
            'amount' => 500,
        ]);

        $service->applyPayment($payment);

        $this->assertDatabaseMissing('advance_balances', [
            'member_id' => $member->id,
        ]);
    }

    public function test_manager_can_adjust_balance_positive(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $controller = app(AdvanceBalanceController::class);
        $storeAdjust = (new \ReflectionClass($controller))->getMethod('storeAdjust');
        $storeAdjust->setAccessible(true);

        $request = AdjustAdvanceBalanceRequest::create(
            route('mess.advance-balances.storeAdjust', $member),
            'POST',
            ['amount' => 750, 'reason' => 'Manual credit for shared groceries']
        );
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $storeAdjust->invoke($controller, $request, $member);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('advance_balances', [
            'member_id' => $member->id,
            'balance' => '750.00',
        ]);
    }

    public function test_manager_can_adjust_balance_negative(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(AdvanceBalanceService::class);
        $service->adjust($member->id, -200.0, 'Outstanding electricity share', $admin->id);

        $this->assertDatabaseHas('advance_balances', [
            'member_id' => $member->id,
            'balance' => '0.00',
            'due_balance' => '200.00',
        ]);
    }

    public function test_zero_amount_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(AdvanceBalanceService::class);

        $this->expectException(\RuntimeException::class);
        $service->adjust($member->id, 0.0, 'Test', $admin->id);
    }

    public function test_carry_forward_adds_to_advance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(AdvanceBalanceService::class);
        $service->carryForward($member->id, '300.00');

        $this->assertDatabaseHas('advance_balances', [
            'member_id' => $member->id,
            'balance' => '300.00',
        ]);
    }

    public function test_carry_forward_subtracts_to_due(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(AdvanceBalanceService::class);
        $service->carryForward($member->id, '-150.00');

        $this->assertDatabaseHas('advance_balances', [
            'member_id' => $member->id,
            'balance' => '0.00',
            'due_balance' => '150.00',
        ]);
    }

    public function test_index_lists_active_members(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        Member::factory()->count(3)->create(['status' => MemberStatus::ACTIVE]);

        $this->actingAs($admin)
            ->get(route('mess.advance-balances.index'))
            ->assertOk();
    }
}
