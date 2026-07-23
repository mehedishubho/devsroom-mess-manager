<?php

namespace Tests\Feature\Mess;

use App\Http\Controllers\Mess\GuestMealController;
use App\Http\Requests\Mess\StoreGuestMealRequest;
use App\Http\Requests\Mess\UpdateGuestMealRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Services\GuestMealService;
use App\Support\MealType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Route;
use Tests\TestCase;

class GuestMealTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_admin_can_view_guest_meals_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        $this->actingAs($admin)
            ->get(route('mess.guest-meals.index'))
            ->assertOk()
            ->assertSee('Guest meals');
    }

    public function test_admin_can_create_guest_meal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);

        $controller = app(GuestMealController::class);
        $reflection = new \ReflectionClass($controller);
        $store = $reflection->getMethod('store');
        $store->setAccessible(true);

        $request = StoreGuestMealRequest::create(route('mess.guest-meals.store'), 'POST', [
            'member_id' => $member->id,
            'guest_name' => 'Visitor X',
            'date' => now()->toDateString(),
            'meal_type' => MealType::LUNCH,
            'quantity' => 2,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->validateResolved();

        $response = $store->invoke($controller, $request);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertDatabaseHas('guest_meals', [
            'guest_name' => 'Visitor X',
            'meal_type' => MealType::LUNCH,
            'quantity' => '2.00',
            'meal_value' => '1.00',
            'charge_amount' => '2.00',
        ]);
    }

    public function test_admin_can_update_guest_meal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());
        $messId = Mess::activeId();
        $member = Member::factory()->create(['mess_id' => $messId, 'status' => 'active']);

        $service = app(GuestMealService::class);
        $guestMeal = $service->create([
            'member_id' => $member->id,
            'guest_name' => 'Old Name',
            'date' => now()->toDateString(),
            'meal_type' => MealType::LUNCH,
            'quantity' => 1,
        ]);

        $controller = app(GuestMealController::class);
        $reflection = new \ReflectionClass($controller);
        $update = $reflection->getMethod('update');
        $update->setAccessible(true);

        $request = UpdateGuestMealRequest::create(route('mess.guest-meals.update', $guestMeal), 'PATCH', [
            'member_id' => $member->id,
            'guest_name' => 'New Name',
            'date' => now()->toDateString(),
            'meal_type' => MealType::DINNER,
            'quantity' => 3,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $admin);
        $request->setRouteResolver(function () use ($guestMeal) {
            $route = new Route('PATCH', 'mess/guest-meals/{guestMeal}', []);
            $route->setParameter('guestMeal', $guestMeal);

            return $route;
        });
        $request->validateResolved();

        $response = $update->invoke($controller, $request, $guestMeal);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $fresh = $guestMeal->fresh();
        $this->assertSame('New Name', $fresh->guest_name);
        $this->assertSame(MealType::DINNER, $fresh->meal_type);
        $this->assertEquals(3.0, (float) $fresh->quantity);
    }
}
