<?php

namespace Tests\Feature\Mess;

use App\Models\AdvanceBalance;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\MemberStatus;
use App\Support\NotificationType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_send_due_reminder_to_member_with_due_balance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $memberUser = User::factory()->create();
        $memberUser->assignRole(Role::where('slug', 'mess-member')->first());
        $member = Member::factory()->create([
            'user_id' => $memberUser->id,
            'status' => MemberStatus::ACTIVE,
        ]);
        AdvanceBalance::factory()->withDue(500)->create(['member_id' => $member->id]);

        $this->actingAs($admin)
            ->post(route('mess.due-reminder.send'), ['member_ids' => [$member->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $memberUser->id,
            'type' => NotificationType::DUE_REMINDER,
        ]);
    }

    public function test_due_reminder_skips_member_without_due_balance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $memberUser = User::factory()->create();
        $member = Member::factory()->create([
            'user_id' => $memberUser->id,
            'status' => MemberStatus::ACTIVE,
        ]);
        // No due balance — only a positive advance balance.
        AdvanceBalance::factory()->withAdvance(1000)->create(['member_id' => $member->id]);

        $this->actingAs($admin)
            ->post(route('mess.due-reminder.send'), ['member_ids' => [$member->id]]);

        $this->assertDatabaseMissing('notifications', ['user_id' => $memberUser->id]);
    }

    public function test_due_reminder_index_lists_only_members_with_due(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $due = Member::factory()->create(['status' => MemberStatus::ACTIVE, 'name' => 'Has Due']);
        $clear = Member::factory()->create(['status' => MemberStatus::ACTIVE, 'name' => 'No Due']);
        AdvanceBalance::factory()->withDue(100)->create(['member_id' => $due->id]);

        $this->actingAs($admin)
            ->get(route('mess.due-reminder.index'))
            ->assertOk()
            ->assertSee('Has Due')
            ->assertDontSee('No Due');
    }

    public function test_regular_member_cannot_access_due_reminder_index(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $response = $this->actingAs($user)->get(route('mess.due-reminder.index'));
        $this->assertSame(403, $response->status());
    }
}
