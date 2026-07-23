<?php

namespace Tests\Feature\Mess;

use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Services\MealOffApprovalService;
use App\Support\MealOffStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealOffApprovalAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_approval_writes_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['mess_id' => $messId]);
        $req = MealOffRequest::create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'from_date' => now()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Travel',
            'status' => MealOffStatus::PENDING,
            'requested_at' => now(),
        ]);

        $service = app(MealOffApprovalService::class);
        $service->approve($req, $admin->id);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => MealOffRequest::class,
            'auditable_id' => $req->id,
            'event' => 'updated',
        ]);
    }
}
