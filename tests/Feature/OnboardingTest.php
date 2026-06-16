<?php

namespace Tests\Feature;

use App\Http\Controllers\OnboardingController;
use App\Http\Requests\Onboarding\CreateMessRequest;
use App\Models\Mess;
use App\Models\Setting;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
    }

    public function test_super_admin_sees_onboarding_when_no_mess(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        $this->actingAs($user)->get(route('onboarding.create'))->assertOk();
    }

    public function test_super_admin_redirected_to_home_when_mess_exists(): void
    {
        Mess::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        $this->actingAs($user)->get(route('onboarding.create'))->assertRedirect(route('home'));
    }

    public function test_admin_cannot_access_onboarding(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user)->get(route('onboarding.create'))->assertForbidden();
    }

    public function test_onboarding_route_is_registered_with_post(): void
    {
        $routes = collect(Route::getRoutes());
        $onboarding = $routes->first(fn ($r) => $r->getName() === 'onboarding.store');
        $this->assertNotNull($onboarding);
        $this->assertContains('POST', $onboarding->methods());
    }

    public function test_create_mess_request_validates_required_fields(): void
    {
        $request = new CreateMessRequest;
        $rules = $request->rules();
        $this->assertContains('required', $rules['name']);
        $this->assertContains('required', $rules['monthly_rent']);
        $this->assertContains('required', $rules['meal_breakfast']);
    }

    public function test_setting_factory_creates_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'super-admin')->first());

        $this->actingAs($user);

        $controller = app(OnboardingController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('store');
        $method->setAccessible(true);

        $request = CreateMessRequest::create('/onboarding', 'POST', [
            'name' => 'Test Mess',
            'address' => '1 Main St',
            'monthly_rent' => 12000,
            'manager_contact' => 'me@test.com',
            'meal_breakfast' => 0.5,
            'meal_lunch' => 1,
            'meal_dinner' => 1,
            'currency' => 'BDT',
            'date_format' => 'DD-MM-YYYY',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        $response = $method->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseHas('messes', ['name' => 'Test Mess']);
        $this->assertEquals(6, Setting::withoutGlobalScopes()->count());
    }
}
