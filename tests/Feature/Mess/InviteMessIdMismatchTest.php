<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\MemberInviteController;
use App\Http\Requests\Mess\InviteMemberRequest;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteMessIdMismatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
    }

    public function test_invite_works_when_mess_is_at_a_non_default_id(): void
    {
        // Simulate the bug: env says id=1, but the actual mess is at id=42.
        config(['mess.active_mess_id' => 1]);
        $mess = Mess::factory()->create(['id' => 42, 'name' => 'Real Mess']);
        Mess::forgetActiveIdCache();

        // Sanity: the env-supplied id is wrong but a mess exists.
        $this->assertFalse(Mess::query()->whereKey(1)->exists());
        $this->assertTrue(Mess::query()->whereKey(42)->exists());

        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        Mail::fake();
        $this->actingAs($admin);

        $controller = app(MemberInviteController::class);
        $method = (new \ReflectionClass($controller))->getMethod('store');
        $method->setAccessible(true);

        $request = InviteMemberRequest::create(
            route('mess.members.invite.store'),
            'POST',
            ['email' => 'new@test.com']
        );
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        // Before the fix: throws QueryException (FK violation).
        // After the fix: returns a redirect.
        $response = $method->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('member_invitations', [
            'email' => 'new@test.com',
            'mess_id' => 42,
        ]);
    }

    public function test_active_id_helper_returns_first_mess(): void
    {
        $this->assertNull(Mess::activeId());

        $mess = Mess::factory()->create();
        Mess::forgetActiveIdCache();
        $this->assertSame((int) $mess->id, Mess::activeId());
    }

    public function test_active_id_helper_falls_back_to_override_when_mess_exists(): void
    {
        config(['mess.active_mess_id' => 99]);
        Mess::factory()->create(['id' => 99]);
        Mess::forgetActiveIdCache();
        $this->assertSame(99, Mess::activeId());
    }

    public function test_active_id_helper_ignores_override_when_mess_missing(): void
    {
        // Override points at id=99, but no mess at id=99 — fall back to
        // the first actual Mess.
        config(['mess.active_mess_id' => 99]);
        $mess = Mess::factory()->create(['id' => 50]);
        Mess::forgetActiveIdCache();
        $this->assertSame(50, Mess::activeId());
    }
}
