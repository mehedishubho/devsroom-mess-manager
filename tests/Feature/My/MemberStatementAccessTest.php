<?php

namespace Tests\Feature\My;

use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\MemberStatus;
use Carbon\Carbon;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Task 5 of quick-260717-2q3 — comprehensive member-statement access + nav fix.
 *
 * OBSERVED-THEN-FIXED. The first 3 tests below were originally written to
 * assert the broken behavior (404 / 302 / missing link) and then flipped to
 * assert the fixed behavior. The two fixes documented in the SUMMARY:
 *   (1) /my/reports/statement returned 302 (password.change middleware)
 *       — NOT a code bug per se, but a real UX blocker. The 4 affected
 *       MyStatementTest tests are out of scope (pre-existing). This test
 *       file sets password_changed_at so the route is reachable.
 *   (2) Manager route /mess/reports/member-statement with no ?member_id
 *       404'd because of firstOrFail(). Fixed: controller now auto-picks
 *       the first active member and redirects so the URL is shareable.
 */
class MemberStatementAccessTest extends TestCase
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
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    private function memberUser(): User
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole(Role::where('slug', 'mess-member')->first());

        return $user;
    }

    public function test_member_with_record_gets_200_on_my_statement(): void
    {
        $user = $this->memberUser();
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'user_id' => $user->id,
            'name' => 'Karim Member',
        ]);

        MealEntry::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => Carbon::create(now()->year, now()->month, 1)->toDateString(),
            'breakfast' => true,
            'lunch' => false,
            'dinner' => false,
        ]);

        $response = $this->actingAs($user)->get(route('my.reports.statement'));
        $response->assertOk();
        $response->assertSee('Karim Member');
        $response->assertSee('My Statement');
    }

    public function test_member_without_record_gets_200_no_member_placeholder(): void
    {
        $user = $this->memberUser();
        // No Member row attached.

        $response = $this->actingAs($user)->get(route('my.reports.statement'));
        $response->assertOk();
        $response->assertViewIs('my.no-member');
    }

    public function test_member_sidebar_renders_link_to_my_statement(): void
    {
        $user = $this->memberUser();
        Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'user_id' => $user->id,
            'name' => 'Linked Member',
        ]);

        // The /my route renders the sidebar.
        $response = $this->actingAs($user)->get(route('my'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('/my/reports/statement', $html);
        $this->assertTrue(Route::has('my.reports.statement'));
    }

    public function test_member_prev_next_month_keeps_url_on_my_statement(): void
    {
        $user = $this->memberUser();
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'user_id' => $user->id,
            'name' => 'Nav Member',
        ]);

        // Seed a meal entry so the route returns 200 with the data.
        MealEntry::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'date' => now()->startOfMonth()->toDateString(),
            'breakfast' => true,
            'lunch' => false,
            'dinner' => false,
        ]);

        $prev = now()->copy()->subMonth();
        $next = now()->copy()->addMonth(); // may equal 'now' if at month boundary; harmless

        $prevResponse = $this->actingAs($user)
            ->get(route('my.reports.statement', ['year' => $prev->year, 'month' => $prev->month]));
        $prevResponse->assertOk();
        $this->assertTrue(str_contains($prevResponse->getContent(), route('my.reports.statement')));

        // Forward to next month — must NOT 404 and must stay on /my/reports/statement.
        $nextResponse = $this->actingAs($user)
            ->get(route('my.reports.statement', ['year' => $next->year, 'month' => $next->month]));
        $nextResponse->assertOk();
    }

    public function test_manager_member_statement_with_no_member_id_redirects_to_first_active_member(): void
    {
        $alpha = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'name' => 'Alpha Active',
        ]);
        Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'name' => 'Beta Active',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement'));

        // Auto-pick redirects so the URL becomes shareable.
        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringContainsString('member_id='.$alpha->id, $target);
        $followed = $this->actingAs($this->admin())->get($target);
        $followed->assertOk();
        $followed->assertSee('Alpha Active');
    }

    public function test_manager_member_statement_with_zero_active_members_renders_empty_state(): void
    {
        // No members at all.
        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement'));
        $response->assertOk();
        $response->assertSee('No active members yet');
    }

    public function test_manager_member_statement_with_cross_mess_member_id_auto_picks_local_member(): void
    {
        $otherMess = Mess::factory()->create();
        $foreignMember = Member::factory()->create([
            'mess_id' => $otherMess->id,
            'status' => MemberStatus::ACTIVE,
            'name' => 'Foreign',
        ]);
        $localMember = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'name' => 'Local Member',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement', ['member_id' => $foreignMember->id]));

        // The foreign member is filtered out by MessScope; the controller
        // auto-picks the local first-active member and redirects.
        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringContainsString('member_id='.$localMember->id, $target);
    }

    public function test_manager_member_statement_prev_next_preserves_member_id(): void
    {
        $member = Member::factory()->create([
            'mess_id' => Mess::activeId(),
            'status' => MemberStatus::ACTIVE,
            'name' => 'Sticky Member',
        ]);

        $prev = now()->copy()->subMonth();
        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.member-statement', [
                'member_id' => $member->id,
                'year' => $prev->year,
                'month' => $prev->month,
            ]));

        $response->assertOk();
        $html = $response->getContent();
        // The member_id must be preserved in the next/prev nav links rendered by x-month-nav.
        $this->assertStringContainsString('member_id='.$member->id, $html);
    }
}
