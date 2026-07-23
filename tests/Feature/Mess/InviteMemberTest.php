<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\MemberInviteController;
use App\Http\Controllers\SetPasswordController;
use App\Http\Requests\Mess\InviteMemberRequest;
use App\Mail\SetPasswordMail;
use App\Models\MemberInvitation;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InviteMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_view_invite_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($user)->get(route('mess.members.invite.create'))->assertOk();
    }

    public function test_admin_invite_creates_user_and_invitation_and_mails(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($admin);

        $controller = app(MemberInviteController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('store');
        $method->setAccessible(true);

        $request = InviteMemberRequest::create(route('mess.members.invite.store'), 'POST', [
            'email' => 'new@test.com',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $method->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('users', ['email' => 'new@test.com']);
        $this->assertDatabaseHas('member_invitations', ['email' => 'new@test.com']);
        Mail::assertSent(SetPasswordMail::class, fn ($m) => $m->hasTo('new@test.com'));
    }

    public function test_member_cannot_invite(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $this->actingAs($user);

        $request = InviteMemberRequest::create(route('mess.members.invite.store'), 'POST', [
            'email' => 'new@test.com',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_set_password_form_loads_for_valid_token(): void
    {
        $token = 'valid-token';
        MemberInvitation::create([
            'mess_id' => config('mess.active_mess_id'),
            'email' => 'new@test.com',
            'token' => $token,
            'invited_by' => User::factory()->create()->id,
            'expires_at' => now()->addHour(),
        ]);

        $this->get(route('password.set.show', ['token' => $token, 'email' => 'new@test.com']))
            ->assertOk();
    }

    public function test_set_password_form_rejects_expired_token(): void
    {
        MemberInvitation::create([
            'mess_id' => config('mess.active_mess_id'),
            'email' => 'new@test.com',
            'token' => 'expired',
            'invited_by' => User::factory()->create()->id,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->get(route('password.set.show', ['token' => 'expired', 'email' => 'new@test.com']));
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_setting_password_activates_user(): void
    {
        $user = User::factory()->create(['email' => 'new@test.com']);
        $inv = MemberInvitation::create([
            'mess_id' => config('mess.active_mess_id'),
            'email' => 'new@test.com',
            'token' => 'valid',
            'invited_by' => User::factory()->create()->id,
            'expires_at' => now()->addHour(),
        ]);

        $controller = app(SetPasswordController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('update');
        $method->setAccessible(true);

        $request = Request::create(route('password.set.update'), 'POST', [
            'token' => 'valid',
            'email' => 'new@test.com',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);
        Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ])->validate();

        $response = $method->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($inv->fresh()->accepted_at);
    }
}
