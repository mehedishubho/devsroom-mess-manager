<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\AuditController;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        Mess::factory()->create();
    }

    public function test_admin_can_view_audit_log(): void
    {
        $mess = Mess::first();
        $mess->update(['name' => 'Renamed']);
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user)->get(route('mess.audit'))->assertOk();
    }

    public function test_audit_log_filters_by_model(): void
    {
        $mess = Mess::first();
        $mess->update(['name' => 'Renamed']);
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($user)
            ->get(route('mess.audit', ['model' => Mess::class]))
            ->assertOk();
    }

    public function test_member_cannot_view_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $this->actingAs($user)->get(route('mess.audit'))->assertForbidden();
    }

    public function test_audit_controller_lists_audits(): void
    {
        $mess = Mess::first();
        $mess->update(['name' => 'Renamed 1']);
        $mess->update(['name' => 'Renamed 2']);

        $controller = app(AuditController::class);
        $view = $controller->index(Request::create(route('mess.audit')));
        $this->assertEquals('mess.audit.index', $view->name());
    }
}
