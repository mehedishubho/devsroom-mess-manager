<?php

namespace Tests\Feature\Dashboard;

use App\Models\Mess;
use App\Models\User;
use App\Services\ChartBucketingService;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        return $admin;
    }

    public function test_default_ranges_applied(): void
    {
        // No query → meal defaults to 30 days back, expense + payment to 6 months back.
        $response = $this->actingAs($this->admin())->get(route('home'));

        $response->assertOk();

        $mealFrom = Carbon::now()->subDays(29)->startOfDay()->toDateString();
        $expenseFrom = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();

        // The default range should appear in the range form's value="" attribute
        $response->assertSee('value="'.$mealFrom.'"', false);
        $response->assertSee('value="'.$expenseFrom.'"', false);
    }

    public function test_custom_range_respected(): void
    {
        $response = $this->actingAs($this->admin())->get(route('home', [
            'meal_from' => '2026-05-01',
            'meal_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $response->assertSee('value="2026-05-01"', false);
        $response->assertSee('value="2026-05-31"', false);
    }

    public function test_autobucket_picks_daily_for_short_range(): void
    {
        $service = app(ChartBucketingService::class);

        $from = Carbon::now();
        $to = $from->copy()->addDays(30);

        $bucket = $service->bucket($from, $to);

        $this->assertSame('day', $bucket['granularity']);
    }

    public function test_autobucket_picks_monthly_for_long_range(): void
    {
        $service = app(ChartBucketingService::class);

        $from = Carbon::now();
        $to = $from->copy()->addDays(400);

        $bucket = $service->bucket($from, $to);

        $this->assertSame('month', $bucket['granularity']);
    }
}
