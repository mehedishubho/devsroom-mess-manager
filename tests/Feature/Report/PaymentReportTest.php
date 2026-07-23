<?php

namespace Tests\Feature\Report;

use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Support\MemberStatus;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReportTest extends TestCase
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

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('mess.reports.payments'))
            ->assertRedirect('/login');
    }

    public function test_member_role_forbidden(): void
    {
        $this->actingAs($this->member())
            ->get(route('mess.reports.payments'))
            ->assertForbidden();
    }

    public function test_manager_sees_payments_list(): void
    {
        $messId = Mess::activeId();
        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Sadia Islam',
        ]);

        $payments = [
            Payment::factory()->create([
                'mess_id' => $messId,
                'member_id' => $member->id,
                'amount' => 1000,
                'method' => PaymentMethod::CASH,
                'type' => PaymentType::BILL_PAYMENT,
                'date' => '2026-06-05',
            ]),
            Payment::factory()->create([
                'mess_id' => $messId,
                'member_id' => $member->id,
                'amount' => 2000,
                'method' => PaymentMethod::BKASH,
                'reference' => 'BK12345',
                'type' => PaymentType::BILL_PAYMENT,
                'date' => '2026-06-10',
            ]),
            Payment::factory()->create([
                'mess_id' => $messId,
                'member_id' => $member->id,
                'amount' => 500,
                'method' => PaymentMethod::NAGAD,
                'reference' => 'NG99999',
                'type' => PaymentType::ADVANCE_DEPOSIT,
                'date' => '2026-06-15',
            ]),
        ];

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments'));

        $response->assertOk();
        $response->assertSee(__('Payment Report'));
        $response->assertSee('Sadia Islam');
        $response->assertSee('BK12345');
        $response->assertSee('NG99999');
    }

    public function test_totals_sum_correct(): void
    {
        $messId = Mess::activeId();
        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
        ]);

        foreach ([100.00, 200.50, 300.25] as $amount) {
            Payment::factory()->create([
                'mess_id' => $messId,
                'member_id' => $member->id,
                'amount' => $amount,
                'method' => PaymentMethod::CASH,
                'type' => PaymentType::BILL_PAYMENT,
                'date' => '2026-06-10',
            ]);
        }

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments'));

        $response->assertOk();
        // 100 + 200.50 + 300.25 = 600.75 — formatted via Money::taka
        $response->assertSee('৳600.75');
    }

    public function test_method_filter_respected(): void
    {
        $messId = Mess::activeId();
        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
        ]);

        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'amount' => 100,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::BILL_PAYMENT,
            'date' => '2026-06-05',
        ]);
        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'amount' => 200,
            'method' => PaymentMethod::BKASH,
            'reference' => 'BK1',
            'type' => PaymentType::BILL_PAYMENT,
            'date' => '2026-06-05',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments', ['method' => PaymentMethod::CASH]));

        $response->assertOk();
        // Only the cash payment's amount appears; total is 100, not 300
        $response->assertSee('৳100.00');
        $response->assertDontSee('৳300.00');
    }

    public function test_member_filter(): void
    {
        $messId = Mess::activeId();
        $alpha = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Alpha Member',
        ]);
        $beta = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Beta Member',
        ]);

        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $alpha->id,
            'amount' => 100,
            'date' => '2026-06-05',
        ]);
        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $beta->id,
            'amount' => 200,
            'date' => '2026-06-05',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments', ['member_id' => $alpha->id]));

        $response->assertOk();
        // Alpha's payment row appears (member name shown in the payments table body).
        $response->assertSee('Alpha Member');
        // Total collected reflects the filter: only Alpha's 100, not Alpha+Beta=300.
        $response->assertSee('৳100.00');
        $response->assertDontSee('৳300.00');
        // Beta's payment amount (200) must NOT appear in the payments table body.
        $response->assertDontSee('৳200.00');
    }

    public function test_this_month_preset_links_present(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments'));

        $response->assertOk();
        $response->assertSee(__('This month'));
        $response->assertSee(__('Last month'));
    }

    public function test_empty_state_when_no_match(): void
    {
        $messId = Mess::activeId();
        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
        ]);
        // Payment exists but outside the queried range
        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => '2025-01-15',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.payments', [
                'from' => '2026-06-01',
                'to' => '2026-06-30',
            ]));

        $response->assertOk();
        $response->assertSee(__('No payments match the current filters.'));
    }
}
