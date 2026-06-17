<?php

namespace Tests\Feature\My;

use App\Http\Controllers\MyController;
use App\Http\Requests\My\StoreMealOffRequest;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\MealOffStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MyMealOffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_member_can_submit_meal_off_request(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['user_id' => $user->id, 'mess_id' => $messId]);

        $controller = app(MyController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('storeMealOff');
        $method->setAccessible(true);

        $request = StoreMealOffRequest::create(route('my.meal-off.store'), 'POST', [
            'from_date' => now()->addDays(1)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Going home for Eid',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        $response = $method->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('meal_off_requests', [
            'member_id' => $member->id,
            'status' => MealOffStatus::PENDING,
        ]);
    }

    public function test_form_request_requires_reason(): void
    {
        $request = StoreMealOffRequest::create(route('my.meal-off.store'), 'POST', [
            'from_date' => now()->addDays(1)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'reason' => '',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('reason', $validator->errors()->toArray());
    }

    public function test_audit_log_writes_on_request_creation(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'user')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['user_id' => $user->id, 'mess_id' => $messId]);

        MealOffRequest::create([
            'mess_id' => config('mess.active_mess_id'),
            'member_id' => $member->id,
            'from_date' => now()->addDays(1)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Test',
            'status' => MealOffStatus::PENDING,
            'requested_at' => now(),
        ]);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => MealOffRequest::class,
            'event' => 'created',
        ]);
    }
}
