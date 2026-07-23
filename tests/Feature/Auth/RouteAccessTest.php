<?php

namespace Tests\Feature\Auth;

use App\Models\Mess;
use App\Models\User;
use App\Models\AppSetting;
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
        Mess::factory()->create();

        $this->actingAs($user)->get('/home')->assertOk();
    }

    public function test_home_returns_403_for_member(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $this->actingAs($user)->get('/home')->assertForbidden();
    }

    public function test_my_returns_200_for_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $this->actingAs($user)->get('/my')->assertOk();
    }

    public function test_my_route_redirects_when_unauthenticated(): void
    {
        $this->get('/my')->assertRedirect('/login');
    }

    public function test_my_returns_403_for_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user)->get('/my')->assertForbidden();
    }

    public function test_dashboard_is_forbidden_for_member(): void
    {
        AppSetting::create([
            'key' => 'installed',
            'value' => ['installed' => true, 'installed_at' => now()->toISOString()],
        ]);

        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }
}
