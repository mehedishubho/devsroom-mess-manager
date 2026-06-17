<?php

namespace Tests\Feature\Mess;

use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_search_endpoint_returns_partial_html(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $messId = Mess::activeId();
        Member::factory()->create(['name' => 'Rahim Ahmed', 'room_or_seat' => 'R-201', 'mess_id' => $messId]);
        Member::factory()->create(['name' => 'Karim Hossain', 'room_or_seat' => 'R-202', 'mess_id' => $messId]);
        Member::factory()->create(['name' => 'Jamal Uddin', 'room_or_seat' => 'R-101', 'mess_id' => $messId]);

        $this->actingAs($admin)
            ->get(route('mess.members.search', ['q' => 'Rahim']))
            ->assertOk()
            ->assertSee('Rahim Ahmed')
            ->assertDontSee('Karim Hossain');
    }

    public function test_search_endpoint_empty_query_returns_all(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $messId = Mess::activeId();
        Member::factory()->count(3)->create(['mess_id' => $messId]);

        $this->actingAs($admin)
            ->get(route('mess.members.search'))
            ->assertOk();
    }
}
