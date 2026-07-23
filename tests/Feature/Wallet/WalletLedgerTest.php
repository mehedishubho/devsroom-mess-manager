<?php

namespace Tests\Feature\Wallet;

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

class WalletLedgerTest extends TestCase
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
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    public function test_manager_can_view_a_member_wallet(): void
    {
        $admin = $this->admin();
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE, 'name' => 'Karim']);

        $this->actingAs($admin)
            ->get(route('mess.members.wallet', $member))
            ->assertOk()
            ->assertSee(__('Wallet'))
            ->assertSee('Karim');
    }

    public function test_member_can_view_own_wallet(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE, 'name' => 'Self']);
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());
        $member->update(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('my.wallet'))
            ->assertOk()
            ->assertSee('Self');
    }

    public function test_wallet_lists_a_payment_and_the_settled_balance(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin); // PaymentService::create reads auth()->id()
        $member = Member::factory()->create(['status' => MemberStatus::ACTIVE]);

        app(PaymentService::class)->create([
            'member_id' => $member->id,
            'date' => now()->toDateString(),
            'amount' => 1500,
            'method' => PaymentMethod::CASH,
            'type' => PaymentType::ADVANCE_DEPOSIT,
        ]);

        $this->actingAs($admin)
            ->get(route('mess.members.wallet', $member))
            ->assertOk()
            // The 1500 advance deposit shows as a credit
            ->assertSee(number_format(1500, 2))
            // ...and the member is in credit by 1500
            ->assertSee(__('Credit'));
    }

    public function test_member_cannot_view_another_member_wallet_route(): void
    {
        // The my.wallet route always resolves the member from the auth user,
        // so there is no IDOR surface — every member sees only their own wallet.
        $memberA = Member::factory()->create(['status' => MemberStatus::ACTIVE, 'name' => 'Alpha']);
        $memberB = Member::factory()->create(['status' => MemberStatus::ACTIVE, 'name' => 'Bravo']);
        $userA = User::factory()->create();
        $userA->assignRole(Role::where('slug', 'mess-member')->first());
        $memberA->update(['user_id' => $userA->id]);

        $this->actingAs($userA)
            ->get(route('my.wallet'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Bravo');
    }
}
