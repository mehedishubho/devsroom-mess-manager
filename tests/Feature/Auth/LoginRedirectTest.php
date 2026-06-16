<?php

namespace Tests\Feature\Auth;

use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
    }

    public function test_closure_returns_dashboard_for_super_admin(): void
    {
        Mess::factory()->create();
        $user = User::factory()->create([
            'email' => 'super@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        Mess::forgetActiveIdCache();

        $this->actingAs($user);
        $url = config('tyro-login.redirects.after_login')();
        $this->assertSame('/dashboard', $url);
    }

    public function test_closure_returns_onboarding_for_super_admin_when_no_mess(): void
    {
        $user = User::factory()->create([
            'email' => 'super-new@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        Mess::forgetActiveIdCache();

        $this->actingAs($user);
        $url = config('tyro-login.redirects.after_login')();
        $this->assertSame(route('onboarding.create'), $url);
    }

    public function test_closure_returns_home_for_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user);
        $url = config('tyro-login.redirects.after_login')();
        $this->assertSame('/home', $url);
    }

    public function test_closure_returns_my_for_user(): void
    {
        $user = User::factory()->create([
            'email' => 'member@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'user')->first());

        $this->actingAs($user);
        $url = config('tyro-login.redirects.after_login')();
        $this->assertSame('/my', $url);
    }
}
