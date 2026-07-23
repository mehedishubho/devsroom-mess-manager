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

    public function test_post_login_redirects_super_admin_to_dashboard(): void
    {
        Mess::factory()->create();
        $user = User::factory()->create([
            'email' => 'super@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        Mess::forgetActiveIdCache();

        $this->actingAs($user)
            ->get('/post-login')
            ->assertRedirect('/dashboard');
    }

    public function test_post_login_redirects_super_admin_to_onboarding_when_no_mess(): void
    {
        $user = User::factory()->create([
            'email' => 'super-new@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        Mess::forgetActiveIdCache();

        $this->actingAs($user)
            ->get('/post-login')
            ->assertRedirect(route('onboarding.create'));
    }

    public function test_post_login_redirects_admin_to_home(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($user)
            ->get('/post-login')
            ->assertRedirect('/home');
    }

    public function test_post_login_redirects_user_to_my(): void
    {
        $user = User::factory()->create([
            'email' => 'member@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $this->actingAs($user)
            ->get('/post-login')
            ->assertRedirect('/my');
    }
}
