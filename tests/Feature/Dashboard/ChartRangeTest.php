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
