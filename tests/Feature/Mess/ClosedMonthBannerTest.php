<?php

namespace Tests\Feature\Mess;

use App\Models\Mess;
use App\Models\MonthlyClosing;
use App\Models\User;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedMonthBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_closing_show_page_includes_month_closed_banner(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $closing = MonthlyClosing::factory()->create([
            'mess_id' => Mess::activeId(),
            'closed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('mess.closings.show', $closing))
            ->assertOk()
            ->assertSee('MONTH CLOSED');
    }

    public function test_home_shows_closed_month_banner_when_current_month_is_closed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $now = Carbon::now();
        MonthlyClosing::factory()->create([
            'mess_id' => Mess::activeId(),
            'year' => $now->year,
            'month' => $now->month,
            'closed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('MONTH CLOSED');
    }

    public function test_home_does_not_show_closed_month_banner_when_month_open(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('MONTH CLOSED');
    }
}
