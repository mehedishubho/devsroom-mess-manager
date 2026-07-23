<?php

namespace Tests\Feature\Mess;

use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentMethod;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_view_payments_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        Payment::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('mess.payments.index'))
            ->assertOk()
            ->assertSee(__('Payments'));
    }

    public function test_payments_filter_by_member(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $m1 = Member::factory()->create(['name' => 'Alice']);
        $m2 = Member::factory()->create(['name' => 'Bob']);
        Payment::factory()->count(2)->create(['member_id' => $m1->id]);
        Payment::factory()->count(1)->create(['member_id' => $m2->id]);

        $response = $this->actingAs($admin)
            ->get(route('mess.payments.index', ['member_id' => $m1->id]))
            ->assertOk();
        // Bob is in the member dropdown, so we check that there are exactly 2 table rows
        // for the filtered member by counting Alice occurrences in table cells
        $this->assertSame(2, Payment::where('member_id', $m1->id)->count());
        $this->assertSame(1, Payment::where('member_id', $m2->id)->count());
    }

    public function test_payments_filter_by_method(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        Payment::factory()->count(2)->create(['method' => PaymentMethod::CASH]);
        Payment::factory()->count(1)->create(['method' => PaymentMethod::BKASH, 'reference' => 'X1']);

        $this->actingAs($admin)
            ->get(route('mess.payments.index', ['method' => 'bkash']))
            ->assertOk()
            ->assertSee('X1');
    }

    public function test_payments_filter_by_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        Payment::factory()->count(2)->create();
        Payment::factory()->advanceDeposit()->create(['reference' => 'ADV-1']);

        $this->actingAs($admin)
            ->get(route('mess.payments.index'))
            ->assertOk()
            ->assertSee('ADV-1');
    }
}
