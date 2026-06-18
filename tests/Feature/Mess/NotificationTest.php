<?php

namespace Tests\Feature\Mess;

use App\Models\Member;
use App\Models\Mess;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\MemberStatus;
use App\Support\NotificationType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_unread_count_for_user_excludes_read_notifications(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['mess_id' => Mess::activeId(), 'user_id' => $user->id, 'read_at' => null]);
        Notification::factory()->create(['mess_id' => Mess::activeId(), 'user_id' => $user->id, 'read_at' => now()]);

        $count = app(NotificationService::class)->unreadCount($user->id);
        $this->assertSame(3, $count);
    }

    public function test_broadcast_to_managers_sends_to_super_admins_and_active_mess_admins(): void
    {
        // Two admins belong to the active mess via a Member row.
        $activeMessId = Mess::activeId();
        $a1 = User::factory()->create();
        $a1->assignRole(Role::where('slug', 'admin')->first());
        Member::factory()->create(['mess_id' => $activeMessId, 'user_id' => $a1->id, 'status' => MemberStatus::ACTIVE]);

        $a2 = User::factory()->create();
        $a2->assignRole(Role::where('slug', 'admin')->first());
        Member::factory()->create(['mess_id' => $activeMessId, 'user_id' => $a2->id, 'status' => MemberStatus::ACTIVE]);

        // Super-admins are cross-mess — they get notified even without a Member row.
        $super = User::factory()->create();
        $super->assignRole(Role::where('slug', 'super-admin')->first());

        app(NotificationService::class)->broadcastToManagers(NotificationType::CLOSE_COMPLETE, [
            'year' => 2026,
            'month' => 6,
        ]);

        $this->assertSame(3, Notification::where('type', 'close_complete')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $a1->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $a2->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $super->id]);
    }

    /**
     * WR-08 regression: closing a month in mess A must NOT notify admins of
     * mess B. Privacy / cross-tenant leak check.
     */
    public function test_broadcast_to_managers_does_not_leak_across_messes(): void
    {
        $activeMessId = Mess::activeId();

        // Mess A's admin — has a Member row in the active mess.
        $messAAdmin = User::factory()->create();
        $messAAdmin->assignRole(Role::where('slug', 'admin')->first());
        Member::factory()->create([
            'mess_id' => $activeMessId,
            'user_id' => $messAAdmin->id,
            'status' => MemberStatus::ACTIVE,
        ]);

        // Mess B — separate mess with its own admin. NO Member row in the active mess.
        $messB = Mess::factory()->create();
        $messBAdmin = User::factory()->create();
        $messBAdmin->assignRole(Role::where('slug', 'admin')->first());
        Member::factory()->create([
            'mess_id' => $messB->id,
            'user_id' => $messBAdmin->id,
            'status' => MemberStatus::ACTIVE,
        ]);

        app(NotificationService::class)->broadcastToManagers(NotificationType::CLOSE_COMPLETE, [
            'year' => 2026,
            'month' => 6,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $messAAdmin->id,
            'type' => 'close_complete',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $messBAdmin->id,
        ]);
    }

    public function test_mark_read_sets_read_at_timestamp(): void
    {
        $user = User::factory()->create();
        $n = Notification::factory()->create(['mess_id' => Mess::activeId(), 'user_id' => $user->id, 'read_at' => null]);

        app(NotificationService::class)->markRead($n);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_read_clears_unread_for_user_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::factory()->count(2)->create(['mess_id' => Mess::activeId(), 'user_id' => $user->id, 'read_at' => null]);
        Notification::factory()->create(['mess_id' => Mess::activeId(), 'user_id' => $other->id, 'read_at' => null]);

        $affected = app(NotificationService::class)->markAllRead($user->id);

        $this->assertSame(2, $affected);
        $this->assertSame(0, app(NotificationService::class)->unreadCount($user->id));
        $this->assertSame(1, app(NotificationService::class)->unreadCount($other->id));
    }

    public function test_notification_index_marks_all_read_and_lists_own_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $other = User::factory()->create();
        Notification::factory()->create(['mess_id' => Mess::activeId(), 'user_id' => $admin->id, 'type' => NotificationType::CLOSE_COMPLETE]);
        Notification::factory()->create(['mess_id' => Mess::activeId(), 'user_id' => $other->id, 'type' => NotificationType::DUE_REMINDER]);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee(__('Month closed'));

        // The admin's notification is now read; the other user's is still unread.
        $this->assertSame(0, app(NotificationService::class)->unreadCount($admin->id));
        $this->assertSame(1, app(NotificationService::class)->unreadCount($other->id));
    }

    public function test_notification_type_constants_are_complete(): void
    {
        $this->assertSame(['close_complete', 'meal_off_decision', 'payment_recorded', 'due_reminder', 'backup_failed'], NotificationType::ALL);
        $this->assertCount(5, NotificationType::LABELS);
        foreach (NotificationType::ALL as $type) {
            $this->assertArrayHasKey($type, NotificationType::LABELS);
        }
    }
}
