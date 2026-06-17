<?php

namespace Tests\Feature\Mess;

use App\Jobs\CloseMonthJob;
use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonthCloseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_dispatch_close_job(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->post(route('mess.close.trigger'), ['year' => 2026, 'month' => 6])
            ->assertRedirect(route('mess.close.index'))
            ->assertSessionHas('success');

        Queue::assertPushed(CloseMonthJob::class, function (CloseMonthJob $job) {
            return $job->year === 2026 && $job->month === 6;
        });
    }

    public function test_second_dispatch_when_month_already_closed_redirects_to_show(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $closing = MonthlyClosing::factory()->create([
            'mess_id' => Mess::activeId(),
            'year' => 2026,
            'month' => 6,
            'closed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('mess.close.trigger'), ['year' => 2026, 'month' => 6])
            ->assertRedirect(route('mess.closings.show', $closing))
            ->assertSessionHas('info');
    }

    public function test_regular_member_cannot_trigger_close(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());

        $response = $this->actingAs($user)
            ->post(route('mess.close.trigger'), ['year' => 2026, 'month' => 6]);

        // role middleware refuses before the request reaches the controller
        $this->assertNotSame(302, $response->status()) || $this->assertSame(403, $response->status());
        $this->assertDatabaseMissing('monthly_closings', ['mess_id' => Mess::activeId()]);
    }

    public function test_close_index_page_loads_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->get(route('mess.close.index'))
            ->assertOk()
            ->assertSee(__('Close month'));
    }

    public function test_sync_dispatch_runs_service_and_creates_closing(): void
    {
        // In the test environment the queue is sync, so dispatching actually runs the job.
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->post(route('mess.close.trigger'), ['year' => 2026, 'month' => 6])
            ->assertRedirect();

        $this->assertDatabaseHas('monthly_closings', [
            'mess_id' => Mess::activeId(),
            'year' => 2026,
            'month' => 6,
        ]);
    }
}
