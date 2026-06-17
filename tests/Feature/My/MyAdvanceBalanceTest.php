<?php

namespace Tests\Feature\My;

use App\Models\AdvanceBalance;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAdvanceBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_member_sees_their_advance_balance(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $member = Member::factory()->create(['user_id' => $user->id]);
        AdvanceBalance::factory()->create([
            'member_id' => $member->id,
            'balance' => 1200,
            'due_balance' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('my', ['tab' => 'balance']))
            ->assertOk()
            ->assertSee('৳1,200.00');
    }

    public function test_member_sees_their_due_balance(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $member = Member::factory()->create(['user_id' => $user->id]);
        AdvanceBalance::factory()->create([
            'member_id' => $member->id,
            'balance' => 0,
            'due_balance' => 500,
        ]);

        $this->actingAs($user)
            ->get(route('my', ['tab' => 'balance']))
            ->assertOk()
            ->assertSee('৳500.00');
    }

    public function test_member_without_balance_sees_zero(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        Member::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('my', ['tab' => 'balance']))
            ->assertOk()
            ->assertSee('৳0.00');
    }
}
