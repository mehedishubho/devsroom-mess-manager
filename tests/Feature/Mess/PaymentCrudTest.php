<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\PaymentController;
use App\Http\Requests\Mess\StorePaymentRequest;
use App\Http\Requests\Mess\UpdatePaymentRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Support\MemberStatus;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_create_bill_payment_cash(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $controller = app(PaymentController::class);
        $store = (new \ReflectionClass($controller))->getMethod('store');
        $store->setAccessible(true);

        $request = StorePaymentRequest::create(route('mess.payments.store'), 'POST', [
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 1500.50,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('payments', [
            'member_id' => $member->id,
            'amount' => '1500.50',
            'method' => 'cash',
            'type' => 'bill_payment',
            'reference' => null,
        ]);
    }

    public function test_bkash_requires_reference(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $request = StorePaymentRequest::create(route('mess.payments.store'), 'POST', [
            'member_id' => 1,
            'date' => now()->toDateString(),
            'amount' => 500,
            'method' => PaymentMethod::BKASH,
            'type' => PaymentType::BILL_PAYMENT,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);

        $validator = \Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('reference', $validator->errors()->toArray());
    }

    public function test_admin_can_create_advance_deposit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $controller = app(PaymentController::class);
        $store = (new \ReflectionClass($controller))->getMethod('store');
        $store->setAccessible(true);

        $request = StorePaymentRequest::create(route('mess.payments.store'), 'POST', [
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 2000,
            'method' => PaymentMethod::BKASH,
            'reference' => 'BK12345',
            'type' => PaymentType::ADVANCE_DEPOSIT,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('payments', [
            'member_id' => $member->id,
            'type' => 'advance_deposit',
            'reference' => 'BK12345',
        ]);
    }

    public function test_admin_can_update_payment(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $payment = Payment::factory()->create();

        $controller = app(PaymentController::class);
        $update = (new \ReflectionClass($controller))->getMethod('update');
        $update->setAccessible(true);

        $request = UpdatePaymentRequest::create(route('mess.payments.update', $payment), 'PATCH', [
            'member_id' => $payment->member_id,
            'date' => $payment->date->toDateString(),
            'amount' => 999.99,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $update->invoke($controller, $request, $payment);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => '999.99']);
    }

    public function test_admin_can_delete_payment(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $payment = Payment::factory()->create();

        $controller = app(PaymentController::class);
        $destroy = (new \ReflectionClass($controller))->getMethod('destroy');
        $destroy->setAccessible(true);

        $request = Request::create(route('mess.payments.destroy', $payment), 'DELETE');
        $request->setUserResolver(fn () => $admin);

        $response = $destroy->invoke($controller, $payment);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_deleting_advance_deposit_reverses_balance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $this->actingAs($admin); // PaymentService::create reads auth()->id()
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        $service = app(\App\Services\PaymentService::class);
        $payment = $service->create([
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 2000,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::ADVANCE_DEPOSIT,
        ]);

        $this->assertDatabaseHas('advance_balances', ['member_id' => $member->id, 'balance' => '2000.00']);

        $service->delete($payment);

        // Deleting MUST reverse the credit — previously the balance stayed inflated.
        $this->assertDatabaseHas('advance_balances', ['member_id' => $member->id, 'balance' => '0.00']);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_member_cannot_create_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());

        $request = StorePaymentRequest::create(route('mess.payments.store'), 'POST', [
            'member_id' => 1, 'date' => now()->toDateString(),
            'amount' => 100, 'method' => PaymentMethod::CASH, 'type' => PaymentType::BILL_PAYMENT,
        ]);
        $request->setContainer(app());
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
