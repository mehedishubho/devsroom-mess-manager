<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\MemberController;
use App\Http\Requests\Mess\StoreMemberRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class MemberAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_creating_a_member_writes_an_audit_entry(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $controller = app(MemberController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreMemberRequest::create(route('mess.members.store'), 'POST', [
            'name' => 'Test Member',
            'mobile' => '01700000000',
            'status' => 'active',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Member::class,
            'event' => 'created',
        ]);
    }
}
