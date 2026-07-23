<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use App\Support\MemberStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plan 05-02 Task 2 — D-08 query-count smoke test (acceptable, not required;
 * locks the verified N+1-safety of MealGridService::buildGridData).
 *
 * MealGridService::buildGridData uses whereIn('member_id', $activeMembers->pluck('id'))
 * for BOTH MealEntry and MealOffRequest (research Example 1, verified). At 50
 * active members, the grid must therefore issue a small FIXED number of queries
 * — NOT 1 + N×3 (the N+1 signature).
 *
 * These tests pin the query count. If a future change to MealGridService (or
 * anything in the /mess/meals request path) regresses to N+1, the count blows
 * past the threshold and this test fails loudly — instead of silently shipping
 * a perf regression that only surfaces at the @50-member budget check.
 *
 * Budget (per D-08/D-10): grid <10 queries at 50 members. Threshold set to <15
 * for headroom (Telescope/Debugbar queries in the test env add a couple of
 * framework reads; the assertion is "no N+1 pattern", not "exactly N queries").
 */
class MealGridQueryCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
    }

    private function adminForMess(Mess $mess): User
    {
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    /**
     * Test 1 — D-10 success #5 / T-05-02-06: /mess/meals at 50 active members
     * runs fewer than 15 queries (locks the whereIn N+1-safety).
     *
     * The N+1 signature would be 1 (members) + 50×3 (one query per meal type
     * per member) = 151+ queries. A safe whereIn implementation runs ~3-6.
     * Threshold of 15 catches any regression while leaving headroom for
     * framework-level queries (auth, session, mess lookup).
     */
    public function test_meal_grid_loads_under_15_queries_at_50_members(): void
    {
        $mess = Mess::factory()->create();
        $admin = $this->adminForMess($mess);

        // Seed 50 active members + today's meal entries (one per member).
        $members = Member::factory()
            ->count(50)
            ->create([
                'mess_id' => $mess->id,
                'status' => MemberStatus::ACTIVE,
            ]);

        $today = now()->toDateString();
        foreach ($members as $member) {
            MealEntry::factory()->create([
                'mess_id' => $mess->id,
                'member_id' => $member->id,
                'date' => $today,
            ]);
        }

        // Flush any queries triggered by the seed / actingAs warm-up.
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('mess.meals.index'));

        $response->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            15,
            $count,
            "Meal grid ran {$count} queries at 50 members — expected < 15 (N+1 regression?). ".
            'MealGridService::buildGridData should use whereIn(\'member_id\', ...) for both '.
            'MealEntry and MealOffRequest, not a per-member loop.'
        );
    }

    /**
     * Test 2 — D-10 success #5: /home (manager dashboard) does NOT issue any
     * repeated `select * from X where id = ?` N+1 pattern when many members
     * exist. DashboardService uses Cache::remember for both dash:counts and
     * (via BillPreviewService) bill-preview keys, so the warm path is a fixed
     * small number of queries regardless of member count.
     */
    public function test_manager_dashboard_does_not_show_n_plus_1_pattern_at_50_members(): void
    {
        $mess = Mess::factory()->create();
        $admin = $this->adminForMess($mess);

        Member::factory()
            ->count(50)
            ->create([
                'mess_id' => $mess->id,
                'status' => MemberStatus::ACTIVE,
            ]);

        // First request: warm the bill-preview + dash:counts caches.
        // (Cache store is `array` in phpunit.xml — per-process — so we must
        // do the cold-then-warm cycle inside one test process.)
        $this->actingAs($admin)->get(route('home'));

        // Second request: measure. The two cache keys should HIT and the
        // query pattern should be a small fixed count, not 1 + N×k.
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('home'));

        $response->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Detect the N+1 signature: any `select * from <table> where id = ?`
        // query that repeats with different bindings. This is the canonical
        // Eloquent lazy-load signature (e.g. $member->relation in a loop).
        $nPlusOneSignatures = [];
        foreach ($queries as $q) {
            $sql = $q['query'] ?? '';
            // Match "select * from `<table>` where `id` = ?" (lazy-load-by-id).
            // whereIn / whereBetween / aggregates are allowed — those are batched.
            if (preg_match('/^select \* from `[a-z_]+` where `id` = \?$/', $sql)) {
                $nPlusOneSignatures[] = $sql;
            }
        }

        $this->assertEmpty(
            $nPlusOneSignatures,
            'Manager dashboard /home issued '.count($nPlusOneSignatures).' "select * from X where id = ?" '.
            'queries — that is the N+1 lazy-load signature. Expected: batched queries '.
            '(whereIn / aggregates / cached reads). offending SQL: '.
            collect($nPlusOneSignatures)->take(3)->implode(' | ')
        );
    }
}
