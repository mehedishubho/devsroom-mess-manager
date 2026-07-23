<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\MessConfigController;
use App\Http\Requests\Mess\UpdateMessRequest;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MessConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
    }

    public function test_admin_can_view_settings_page(): void
    {
        $mess = Mess::factory()->create();
        $user = $this->loginAsRole('admin');

        $this->actingAs($user)->get(route('mess.settings.edit'))->assertOk();
    }

    public function test_admin_can_update_mess_settings(): void
    {
        $mess = Mess::factory()->create(['name' => 'Old']);
        $user = $this->loginAsRole('admin');

        $controller = app(MessConfigController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('update');
        $method->setAccessible(true);

        $request = UpdateMessRequest::create(route('mess.settings.update'), 'PATCH', [
            'name' => 'New Name',
            'address' => '123 Main St',
            'monthly_rent' => 10000,
            'manager_contact' => 'me@test.com',
            'status' => 'active',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        $response = $method->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseHas('messes', ['id' => $mess->id, 'name' => 'New Name']);
    }

    public function test_settings_update_validates_required_fields(): void
    {
        Mess::factory()->create();
        $user = $this->loginAsRole('admin');

        $this->actingAs($user)
            ->get(route('mess.settings.edit'))
            ->assertOk();

        $request = UpdateMessRequest::create(route('mess.settings.update'), 'PATCH', [
            'name' => '',
            'monthly_rent' => 'not-a-number',
            'status' => 'invalid',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('monthly_rent', $validator->errors()->toArray());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_member_cannot_view_settings_page(): void
    {
        Mess::factory()->create();
        $user = $this->loginAsRole('mess-member');

        $this->actingAs($user)->get(route('mess.settings.edit'))->assertForbidden();
    }

    private function loginAsRole(string $slug)
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', $slug)->first());

        return $user;
    }
}
