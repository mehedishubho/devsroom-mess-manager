<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuestMeal;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Support\MemberStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyDashboardTest extends TestCase
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

    private function memberUser(Member $member): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());
        $member->update(['user_id' => $user->id]);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        return $admin;
    }

    public function test_member_sees_overview_landing_by_default(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);
        $user = $this->memberUser($member);

        $response = $this->actingAs($user)->get(route('my'));

        $response->assertOk();
        $response->assertSee(__('Overview'));
        $response->assertSee(__('My Meals (this month)'));
        $response->assertSee(__('My Bill (this month)'));
        $response->assertSee(__('My balance'));
        $response->assertSee(__('My Payment History'));
    }

    public function test_member_sees_reports_tab(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);
        $user = $this->memberUser($member);

        $response = $this->actingAs($user)->get(route('my', ['tab' => 'reports']));

        $response->assertOk();
        $response->assertSee(__('My reports'));
        $response->assertSee(route('my.reports.statement'));
        $response->assertSee(route('my.reports.monthly'));
    }

    public function test_member_with_no_member_record_sees_empty_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        $response = $this->actingAs($user)->get(route('my'));

        $response->assertOk();
        $response->assertSee('Your mess account is not set up');
    }

    public function test_my_meals_excludes_guest_meals(): void
    {
        // Open Question #3 LOCKED: My Meals card counts regular B/L/D only.
        // 3 meal entries (each B+L+D = 2.5) = 7.5 total. 2 guest meals
        // (charge_amount 100 each) must NOT inflate the My Meals value.
        // meal_entries has UNIQUE(mess_id, member_id, date) — use 3 distinct dates.
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);
        $user = $this->memberUser($member);

        $baseDate = Carbon::now()->startOfMonth();
        foreach ([0, 1, 2] as $offset) {
            MealEntry::factory()->create([
                'mess_id' => Mess::activeId(),
                'member_id' => $member->id,
                'date' => $baseDate->copy()->addDays($offset)->toDateString(),
                'breakfast' => true,
                'lunch' => true,
                'dinner' => true,
            ]);
        }
        GuestMeal::factory()->count(2)->create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => $baseDate->toDateString(),
            'charge_amount' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('my'));

        $response->assertOk();
        // 3 × (0.5 + 1 + 1) = 7.5 — guest meals (charge_amount=100) excluded
        $response->assertSee(number_format(7.5, 1));
    }

    public function test_my_payment_history_lists_recent_payments(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);
        $user = $this->memberUser($member);

        Payment::factory()->create([
            'member_id' => $member->id,
            'amount' => 1234.56,
        ]);

        $response = $this->actingAs($user)->get(route('my'));

        $response->assertOk();
        // The Overview "My Payment History" card shows amount + method
        // (reference is reserved for the full Payment History tab).
        $response->assertSee('৳1,234.56');
        $response->assertSee(__('View all'));
    }

    public function test_manager_forbidden_on_my_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('my'))
            ->assertForbidden();
    }
}
