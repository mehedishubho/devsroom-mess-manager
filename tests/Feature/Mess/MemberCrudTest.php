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
        $user->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($user)
            ->get(route('mess.members.index'))
            ->assertOk()
            ->assertSee(__('Members'));
    }

    public function test_admin_can_create_member(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

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
        $admin->assignRole(Role::where('slug', 'manager')->first());

        $photo = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $controller = app(MemberController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'With Account',
            'mobile' => '01711111111',
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

    public function test_member_create_links_existing_user_on_duplicate_email_instead_of_500(): void
    {
        // Regression: users.email is GLOBALLY unique, while members.email is
        // only unique per-mess. If a user with the member's email already
        // exists (leftover from a prior failed create — the old assignRole 500
        // committed the User before throwing — or from an invite), User::create
        // threw a duplicate-key QueryException AFTER the Member committed,
        // surfacing as a 500: member visible under /mess/members, no user under
        // /dashboard/users. firstOrCreate must link to the existing user.
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $existing = User::factory()->create(['email' => 'dup@test.com', 'name' => 'Already Here']);

        $controller = app(MemberController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'New Member',
            'mobile' => '01722222222',
            'email' => 'dup@test.com',
            'status' => 'active',
            'create_account' => true,
            'password' => 'supersecret',
            'password_confirmation' => 'supersecret',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $member = Member::where('email', 'dup@test.com')->first();
        $this->assertNotNull($member, 'Member should be created even when its email matches an existing user.');
        $this->assertEquals($existing->id, $member->user_id, 'Member should be linked to the existing user.');
        $this->assertSame(1, User::where('email', 'dup@test.com')->count(), 'No duplicate user should be created.');
    }

    public function test_admin_can_update_member(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
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
            ['name' => 'New Name', 'mobile' => '01733333333', 'status' => 'active']
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
        $admin->assignRole(Role::where('slug', 'manager')->first());
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
        $member->assignRole(Role::where('slug', 'mess-member')->first());

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

    public function test_create_account_requires_an_email(): void
    {
        // A phone-only member (no email) + "create account" must fail validation
        // with a clear "email required" error — NOT 500 on a NOT NULL violation
        // at User::create (users.email is NOT NULL).
        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'Phone Only',
            'mobile' => '01700000000',
            'status' => 'active',
            'create_account' => true,
            'password' => 'supersecret',
            'password_confirmation' => 'supersecret',
            // intentionally no email
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_member_phone_is_required_and_unique(): void
    {
        // Phone is the member's unique identifier (email optional). A second
        // member with the same phone in the same mess must be rejected, and a
        // member with no phone must be rejected.
        Member::factory()->create(['mobile' => '01755555555']);

        // Duplicate phone -> rejected.
        $dup = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'Dup Phone',
            'mobile' => '01755555555',
            'status' => 'active',
        ]);
        $dup->setContainer(app());
        $dup->setRedirector(app('redirect'));
        $validator = Validator::make($dup->all(), $dup->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mobile', $validator->errors()->toArray());

        // Missing phone -> rejected.
        $missing = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'No Phone',
            'status' => 'active',
        ]);
        $missing->setContainer(app());
        $missing->setRedirector(app('redirect'));
        $validator = Validator::make($missing->all(), $missing->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mobile', $validator->errors()->toArray());
    }
}
