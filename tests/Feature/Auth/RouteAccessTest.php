<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
    }

    public function test_home_requires_auth(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_home_returns_200_for_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user)->get('/home')->assertOk();
    }

    public function test_home_returns_403_for_member(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());

        $this->actingAs($user)->get('/home')->assertForbidden();
    }

    public function test_my_returns_200_for_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());

        $this->actingAs($user)->get('/my')->assertOk();
    }

    public function test_my_returns_403_for_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user)->get('/my')->assertForbidden();
    }
}
