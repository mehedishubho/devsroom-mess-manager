<?php

namespace Tests\Feature;

use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
    }

    public function test_closure_routes_super_admin_to_onboarding_when_no_mess(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        $this->actingAs($user);

        $url = config('tyro-login.redirects.after_login')();
        $this->assertSame(route('onboarding.create'), $url);
    }

    public function test_closure_routes_super_admin_to_dashboard_when_mess_exists(): void
    {
        Mess::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        $this->actingAs($user);

        $url = config('tyro-login.redirects.after_login')();
        $this->assertSame('/dashboard', $url);
    }

    public function test_ensure_mess_middleware_skips_onboarding_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        $this->actingAs($user);

        $response = $this->get(route('onboarding.create'));
        $response->assertOk();
    }

    public function test_manager_redirected_to_onboarding_when_no_mess(): void
    {
        // The post-login closure for the manager role returns /home.
        // EnsureMessExists then intercepts the /home request and redirects
        // to /onboarding because no mess exists.
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());
        $this->actingAs($user);

        $response = $this->get('/home');
        $response->assertRedirect(route('onboarding.create'));
    }
}
