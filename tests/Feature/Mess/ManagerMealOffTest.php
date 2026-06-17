<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\ManagerMealOffController;
use App\Http\Requests\Mess\StoreManagerMealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\MealOffStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class ManagerMealOffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_submit_meal_off_for_member(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['mess_id' => $messId]);

        $controller = app(ManagerMealOffController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('store');
        $method->setAccessible(true);

        $request = StoreManagerMealOffRequest::create(route('mess.members.meal-off.store', $member), 'POST', [
            'from_date' => now()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'On tour',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $method->invoke($controller, $request, $member);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('meal_off_requests', [
            'member_id' => $member->id,
            'status' => MealOffStatus::PENDING,
        ]);
    }

    public function test_member_cannot_submit_meal_off_for_another_member(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $member = Member::factory()->create();

        $request = StoreManagerMealOffRequest::create(route('mess.members.meal-off.store', $member), 'POST', [
            'from_date' => now()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Test',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
