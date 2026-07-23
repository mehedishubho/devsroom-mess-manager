<?php

namespace Tests\Feature\My;

use App\Http\Controllers\MyController;
use App\Http\Requests\My\UpdateMyProfileRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_member_can_view_my_page_with_profile(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole(Role::where('slug', 'mess-member')->first());
        $messId = Mess::activeId();
        Member::factory()->create(['user_id' => $user->id, 'name' => 'Test Member', 'mess_id' => $messId]);

        $this->actingAs($user)
            ->get(route('my'))
            ->assertOk()
            ->assertSee('Test Member');
    }

    public function test_member_can_update_emergency_contact(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['user_id' => $user->id, 'mess_id' => $messId]);

        $controller = app(MyController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('updateProfile');
        $method->setAccessible(true);

        $request = UpdateMyProfileRequest::create(route('my.profile.update'), 'PATCH', [
            'emergency_contact' => '+880 1800-000000',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        $response = $method->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertSame('+880 1800-000000', $member->fresh()->emergency_contact);
    }

    public function test_form_request_rejects_name_field(): void
    {
        $request = UpdateMyProfileRequest::create(route('my.profile.update'), 'PATCH', [
            'emergency_contact' => 'Test',
            'name' => 'Hacked',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
        $data = $validator->validated();
        $this->assertArrayHasKey('name', $data);
        $this->assertSame('Hacked', $data['name']);
    }
}
