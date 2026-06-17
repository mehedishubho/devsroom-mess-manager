<?php

namespace Tests\Feature\My;

use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_member_sees_only_their_payments(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $member = Member::factory()->create(['user_id' => $user->id]);

        Payment::factory()->create(['member_id' => $member->id, 'reference' => 'MINE-1']);
        Payment::factory()->create(['reference' => 'OTHER-1']);

        $response = $this->actingAs($user)->get(route('my', ['tab' => 'payments']));
        $response->assertOk();
        $response->assertSee('MINE-1');
        $response->assertDontSee('OTHER-1');
    }

    public function test_member_history_does_not_show_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $member = Member::factory()->create(['user_id' => $user->id]);
        Payment::factory()->count(3)->create(['member_id' => $member->id]);

        $response = $this->actingAs($user)->get(route('my', ['tab' => 'payments']));
        $response->assertDontSee('name="method"', false);
    }
}
