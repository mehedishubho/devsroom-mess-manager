<?php

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyMonthlyReportTest extends TestCase
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
        $user->assignRole(Role::where('slug', 'user')->first());
        $member->update(['user_id' => $user->id]);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        return $admin;
    }

    public function test_member_views_aggregates_only(): void
    {
        $messId = Mess::activeId();
        $year = now()->year;
        $month = now()->month;
        $date = Carbon::create($year, $month, 1)->toDateString();

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
        ]);
        $user = $this->memberUser($member);

        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
        ]);
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'date' => $date,
            'amount' => 3000,
        ]);
        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $date,
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('my.reports.monthly', ['year' => $year, 'month' => $month]));

        $response->assertOk();
        $response->assertSee(__('Monthly Report'));
        $response->assertSee(__('Meal rate'));
        $response->assertSee(__('Total bazar'));
        $response->assertSee(__('Total fixed'));
    }

    public function test_member_monthly_has_no_per_member_table(): void
    {
        // D-19 guard: another member's name must NOT appear in the aggregates-only view
        $messId = Mess::activeId();
        $self = Member::factory()->create([
            'mess_id' => $messId,
            'name' => 'Self Person',
        ]);
        Member::factory()->create([
            'mess_id' => $messId,
            'name' => 'Peer Hidden',
        ]);
        $user = $this->memberUser($self);

        $response = $this->actingAs($user)
            ->get(route('my.reports.monthly'));

        $response->assertOk();
        $response->assertDontSee('Peer Hidden');
        $response->assertDontSee('Self Person'); // self also hidden — no per-member table at all
    }

    public function test_member_cannot_view_per_member_dues(): void
    {
        // Per-member due amounts must NOT be exposed. Total due is shown
        // (aggregate), but a specific peer's due is not.
        $messId = Mess::activeId();
        $self = Member::factory()->create([
            'mess_id' => $messId,
            'name' => 'Self X',
        ]);
        Member::factory()->create([
            'mess_id' => $messId,
            'name' => 'Peer Y',
        ]);
        $user = $this->memberUser($self);

        $response = $this->actingAs($user)
            ->get(route('my.reports.monthly'));

        $response->assertOk();
        // The member-name table header must not appear as a per-member column header
        $response->assertDontSee('<th>'.__('Member').'</th>', false);
    }

    public function test_manager_forbidden_on_member_monthly(): void
    {
        $this->actingAs($this->admin())
            ->get(route('my.reports.monthly'))
            ->assertForbidden();
    }

    public function test_unauthenticated_redirected(): void
    {
        $this->get(route('my.reports.monthly'))
            ->assertRedirect('/login');
    }
}
