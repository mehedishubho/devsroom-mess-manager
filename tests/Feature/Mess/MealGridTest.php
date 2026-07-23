<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\MealGridController;
use App\Http\Requests\Mess\BulkSaveMealEntriesRequest;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Services\MealGridService;
use App\Support\MealOffStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class MealGridTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_view_meal_grid(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $messId = Mess::activeId();
        Member::factory()->count(3)->create(['mess_id' => $messId, 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('mess.meals.index'))
            ->assertOk()
            ->assertSee('Daily meal grid');
    }

    public function test_bulk_save_creates_meal_entries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $messId = Mess::activeId();
        $member1 = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);
        $member2 = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);

        $controller = app(MealGridController::class);
        $reflection = new \ReflectionClass($controller);
        $save = $reflection->getMethod('save');
        $save->setAccessible(true);

        $date = Carbon::now(config('app.timezone'))->toDateString();
        $request = BulkSaveMealEntriesRequest::create(route('mess.meals.save'), 'POST', [
            'date' => $date,
            'entries' => [
                ['member_id' => $member1->id, 'breakfast' => 1, 'lunch' => 1, 'dinner' => 0],
                ['member_id' => $member2->id, 'breakfast' => 0, 'lunch' => 1, 'dinner' => 1],
            ],
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $save->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('meal_entries', [
            'member_id' => $member1->id,
            'breakfast' => 1,
            'lunch' => 1,
            'dinner' => 0,
        ]);
        $this->assertDatabaseHas('meal_entries', [
            'member_id' => $member2->id,
            'breakfast' => 0,
            'lunch' => 1,
            'dinner' => 1,
        ]);
    }

    public function test_bulk_save_skips_members_on_approved_meal_off(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);
        $today = Carbon::now(config('app.timezone'))->toDateString();

        MealOffRequest::create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'from_date' => $today,
            'to_date' => $today,
            'reason' => 'Test',
            'status' => MealOffStatus::APPROVED,
            'requested_at' => now(),
            'acted_at' => now(),
            'acted_by' => $admin->id,
        ]);

        $controller = app(MealGridController::class);
        $reflection = new \ReflectionClass($controller);
        $save = $reflection->getMethod('save');
        $save->setAccessible(true);

        $request = BulkSaveMealEntriesRequest::create(route('mess.meals.save'), 'POST', [
            'date' => $today,
            'entries' => [
                ['member_id' => $member->id, 'breakfast' => 1, 'lunch' => 1, 'dinner' => 1],
            ],
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $save->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseMissing('meal_entries', [
            'member_id' => $member->id,
            'date' => $today,
        ]);
    }

    public function test_grid_data_marks_meal_off_members_as_non_editable(): void
    {
        $messId = Mess::activeId();
        $member = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);
        $today = Carbon::now(config('app.timezone'));

        MealOffRequest::create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'from_date' => $today->toDateString(),
            'to_date' => $today->addDays(2)->toDateString(),
            'reason' => 'Test',
            'status' => MealOffStatus::APPROVED,
            'requested_at' => now(),
            'acted_at' => now(),
            'acted_by' => User::factory()->create()->id,
        ]);

        $service = app(MealGridService::class);
        $data = $service->buildGridData($today);

        $row = $data['members']->firstWhere('member.id', $member->id);
        $this->assertFalse($row->editable);
    }
}
