<?php

namespace Tests\Feature\My;

use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyBillPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_member_sees_own_bill_preview(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole(Role::where('slug', 'mess-member')->first());
        Member::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('my', ['tab' => 'bill-preview']))
            ->assertOk();
    }

    public function test_member_without_mess_sees_placeholder(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        // A user with no Member record (no mess linkage) lands on the
        // no-member screen — bill preview is unreachable in that state.
        $this->actingAs($user)
            ->get(route('my', ['tab' => 'bill-preview']))
            ->assertOk()
            ->assertSee('Your mess account is not set up');
    }
}
