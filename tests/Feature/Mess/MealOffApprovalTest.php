<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\MealOffApprovalController;
use App\Http\Requests\Mess\ApproveMealOffRequest;
use App\Http\Requests\Mess\RejectMealOffRequest;
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

class MealOffApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_view_approval_queue(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($admin)
            ->get(route('mess.meal-off.index'))
            ->assertOk()
            ->assertSee('Meal off approval');
    }

    public function test_admin_can_approve_meal_off(): void
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

        $controller = app(MealOffApprovalController::class);
        $reflection = new \ReflectionClass($controller);
        $approve = $reflection->getMethod('approve');
        $approve->setAccessible(true);

        $request = ApproveMealOffRequest::create(route('mess.meal-off.approve', $req), 'PATCH');
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $approve->invoke($controller, $request, $req);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertSame(MealOffStatus::APPROVED, $req->fresh()->status);
        $this->assertNotNull($req->fresh()->acted_at);
        $this->assertSame($admin->id, $req->fresh()->acted_by);
    }

    public function test_admin_can_reject_meal_off_with_reason(): void
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

        $controller = app(MealOffApprovalController::class);
        $reflection = new \ReflectionClass($controller);
        $reject = $reflection->getMethod('reject');
        $reject->setAccessible(true);

        $request = RejectMealOffRequest::create(route('mess.meal-off.reject', $req), 'PATCH', [
            'rejection_reason' => 'Travel dates are during month-close',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $reject->invoke($controller, $request, $req);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $fresh = $req->fresh();
        $this->assertSame(MealOffStatus::REJECTED, $fresh->status);
        $this->assertSame('Travel dates are during month-close', $fresh->rejection_reason);
    }

    public function test_rejection_requires_reason(): void
    {
        $request = RejectMealOffRequest::create('/mess/meal-off/1/reject', 'PATCH', [
            'rejection_reason' => '',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('rejection_reason', $validator->errors()->toArray());
    }
}
