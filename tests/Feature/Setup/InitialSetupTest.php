<?php

namespace Tests\Feature\Setup;

use App\Models\AppSetting;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_setup_before_install(): void
    {
        $this->get('/')
            ->assertRedirect(route('setup.create'));
    }

    public function test_guest_root_redirects_to_login_after_install(): void
    {
        $this->markInstalled();

        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_authenticated_installed_admin_root_redirects_to_dashboard(): void
    {
        $this->seedTyroRoles();
        $this->markInstalled();

        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_setup_creates_first_super_admin_and_marks_app_installed(): void
    {
        $this->post(route('setup.store'), [
            'name' => 'Initial Admin',
            'email' => 'admin@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('roles', ['slug' => 'super-admin']);
        $this->assertTrue((bool) AppSetting::where('key', 'installed')->firstOrFail()->value['installed']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_setup_get_redirects_after_install(): void
    {
        $this->markInstalled();

        $this->get(route('setup.create'))
            ->assertRedirect('/dashboard');
    }

    public function test_setup_post_returns_404_after_install(): void
    {
        $this->markInstalled();

        $this->post(route('setup.store'), [
            'name' => 'Second Admin',
            'email' => 'second@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertNotFound();
    }

    public function test_super_admin_still_goes_to_onboarding_after_setup_when_no_mess_exists(): void
    {
        $this->seedTyroRoles();
        $this->markInstalled();

        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());
        Mess::forgetActiveIdCache();

        $this->actingAs($user)
            ->get('/post-login')
            ->assertRedirect(route('onboarding.create'));
    }

    private function markInstalled(): void
    {
        AppSetting::create([
            'key' => 'installed',
            'value' => [
                'installed' => true,
                'installed_at' => now()->toISOString(),
            ],
        ]);
    }
}
