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

class MemberStatementTest extends TestCase
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

    private function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        return $user;
    }

    public function test_manager_views_active_mess_member(): void
    {
        $messId = Mess::activeId();
        $year = now()->year;
        $month = now()->month;
        $date = Carbon::create($year, $month, 1)->toDateString();

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Karim Ahmed',
        ]);

        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $date,
            'breakfast' => true,
            'lunch' => true,
            'dinner' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement', [
                'member_id' => $member->id,
                'year' => $year,
                'month' => $month,
            ]));

        $response->assertOk();
        $response->assertSee('Karim Ahmed');
        $response->assertSee(__('Meal rate'));
        $response->assertSee(__('Daily meals'));
        // The meal-rate math line: rate × meals = meal cost
        $response->assertSee('/ '.__('meal'));
        $response->assertSee('×');
    }

    public function test_manager_cannot_view_cross_mess_member(): void
    {
        // Task 5 (quick-260717-2q3) — behavior changed: a foreign member_id
        // no longer 404s (which the manager experienced as a broken link).
        // Instead, the controller falls through to auto-pick the first
        // active member of the active mess — the URL becomes shareable.
        // Cross-mess DATA is still protected: MessScope on Member::where('id', $foreignId)
        // returns null, so no foreign data ever reaches the view.
        $otherMess = Mess::factory()->create();
        $foreignMember = Member::factory()->create([
            'mess_id' => $otherMess->id,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Foreign Member',
        ]);
        // Seed a local member so the auto-pick has a valid target.
        $localMember = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'name' => 'Local Member',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement', [
                'member_id' => $foreignMember->id,
                'year' => now()->year,
                'month' => now()->month,
            ]));

        // Cross-mess member resolves to null under MessScope; controller
        // auto-picks the local member and redirects (no 404, no foreign data leak).
        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringContainsString('member_id='.$localMember->id, $target);
        $this->assertStringNotContainsString('member_id='.$foreignMember->id, $target);
    }

    public function test_member_role_forbidden(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
        ]);

        $this->actingAs($this->member())
            ->get(route('mess.reports.member-statement', [
                'member_id' => $member->id,
                'year' => now()->year,
                'month' => now()->month,
            ]))
            ->assertForbidden();
    }

    public function test_member_role_forbidden_on_monthly(): void
    {
        // Sanity check — member role is blocked on /mess/reports/* overall
        $this->actingAs($this->member())
            ->get(route('mess.reports.member-statement'))
            ->assertForbidden();
    }

    public function test_statement_excludes_advance_applied_display(): void
    {
        // Pitfall 3 guard: the view MUST NOT render the advance_applied value
        $messId = Mess::activeId();
        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
        ]);
        MealEntry::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => Carbon::create(now()->year, now()->month, 1)->toDateString(),
            'breakfast' => true,
            'lunch' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement', [
                'member_id' => $member->id,
                'year' => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        // The literal "advance_applied" must not appear as a label or raw value anywhere
        $response->assertDontSee('advance_applied');
    }
}
