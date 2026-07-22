<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\MemberController;
use App\Http\Requests\Mess\StoreMemberRequest;
use App\Http\Requests\Mess\UpdateMemberRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MemberCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_view_members_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user)
            ->get(route('mess.members.index'))
            ->assertOk()
            ->assertSee(__('Members'));
    }

    public function test_admin_can_create_member(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $photo = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $controller = app(MemberController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'Test Member',
            'mobile' => '01700000000',
            'email' => 'test@test.com',
            'status' => 'active',
            'room_or_seat' => 'R-201',
        ], [], ['photo' => $photo]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $member = Member::where('email', 'test@test.com')->first();
        $this->assertNotNull($member);
        $this->assertSame('R-201', $member->room_or_seat);
        $this->assertNotNull($member->photo_path);
        Storage::disk('public')->assertExists($member->photo_path);
    }

    public function test_photo_is_stored_when_account_created_too(): void
    {
        // Regression for the "member image never shows" bug: the create_account
        // branch returned before storePhoto(), so a photo uploaded at the same
        // time as account creation was silently dropped.
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $photo = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $controller = app(MemberController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'With Account',
            'email' => 'acct@test.com',
            'status' => 'active',
            'create_account' => true,
            'password' => 'supersecret',
            'password_confirmation' => 'supersecret',
        ], [], ['photo' => $photo]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $member = Member::where('email', 'acct@test.com')->first();
        $this->assertNotNull($member);
        $this->assertNotNull($member->user_id, 'Login account was not linked to the member.');
        $this->assertNotNull($member->photo_path, 'Photo was dropped when create_account was checked.');
        Storage::disk('public')->assertExists($member->photo_path);
    }

    public function test_admin_can_update_member(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['name' => 'Old Name']);

        $controller = app(MemberController::class);
        $reflection = new \ReflectionClass($controller);
        $update = $reflection->getMethod('update');
        $update->setAccessible(true);

        $route = new Route('PATCH', 'mess/members/{member}', []);
        $route->bind(Request::create(''));
        $route->setParameter('member', $member);

        $request = UpdateMemberRequest::create(
            route('mess.members.update', $member),
            'PATCH',
            ['name' => 'New Name', 'status' => 'active']
        );
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->setRouteResolver(fn () => $route);
        $request->validateResolved();

        $response = $update->invoke($controller, $request, $member);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertSame('New Name', $member->fresh()->name);
    }

    public function test_admin_can_deactivate_member(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $member = Member::factory()->create(['status' => 'active']);

        $controller = app(MemberController::class);
        $reflection = new \ReflectionClass($controller);
        $destroy = $reflection->getMethod('destroy');
        $destroy->setAccessible(true);

        $response = $destroy->invoke($controller, $member);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertSame('inactive', $member->fresh()->status);
    }

    public function test_member_cannot_create_member(): void
    {
        $member = User::factory()->create();
        $member->assignRole(Role::where('slug', 'user')->first());

        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'Test',
            'status' => 'active',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $member);

        $this->assertFalse($request->authorize());
    }

    public function test_photo_validation_rejects_non_image(): void
    {
        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'Test',
            'status' => 'active',
        ], [], ['photo' => UploadedFile::fake()->create('not-image.txt', 100)]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('photo', $validator->errors()->toArray());
    }
}
