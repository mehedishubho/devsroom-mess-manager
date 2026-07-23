<?php

namespace Tests\Feature\Report;

use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\MemberStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyStatementTest extends TestCase
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
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    public function test_member_views_own_statement(): void
    {
        $messId = Mess::activeId();
        $year = now()->year;
        $month = now()->month;
        $date = Carbon::create($year, $month, 1)->toDateString();

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Self Member',
        ]);
        $user = $this->memberUser($member);

        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $date,
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('my.reports.statement', ['year' => $year, 'month' => $month]));

        $response->assertOk();
        $response->assertSee('Self Member');
        $response->assertSee(__('Daily meals'));
        // The meal-rate math line: rate × meals = meal cost
        $response->assertSee('/ '.__('meal'));
        $response->assertSee('×');
    }

    public function test_member_cannot_view_other_member_statement(): void
    {
        // IDOR guard: acting as member A, with ?member_id=B in URL — controller
        // MUST ignore the query param and only show A's data.
        $memberA = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'name' => 'Alpha Self',
        ]);
        $memberB = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'name' => 'Beta Other',
        ]);
        $userA = $this->memberUser($memberA);

        $response = $this->actingAs($userA)
            ->get(route('my.reports.statement', ['member_id' => $memberB->id]));

        $response->assertOk();
        $response->assertSee('Alpha Self');
        $response->assertDontSee('Beta Other');
    }

    public function test_manager_forbidden_on_member_routes(): void
    {
        $this->actingAs($this->admin())
            ->get(route('my.reports.statement'))
            ->assertForbidden();
    }

    public function test_unauthenticated_redirected(): void
    {
        $this->get(route('my.reports.statement'))
            ->assertRedirect('/login');
    }

    public function test_statement_month_picker_works(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'name' => 'April Member',
        ]);
        $user = $this->memberUser($member);

        $response = $this->actingAs($user)
            ->get(route('my.reports.statement', ['year' => 2026, 'month' => 4]));

        $response->assertOk();
        $response->assertSee('April 2026');
    }

    public function test_statement_excludes_advance_applied_display(): void
    {
        // Pitfall 3 guard: member view MUST NOT render advance_applied
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);
        $user = $this->memberUser($member);

        MealEntry::factory()->create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => Carbon::create(now()->year, now()->month, 1)->toDateString(),
            'breakfast' => true,
            'lunch' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('my.reports.statement'));

        $response->assertOk();
        $response->assertDontSee('advance_applied');
    }
}
