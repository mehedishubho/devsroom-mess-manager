<?php

namespace Database\Seeders;

use App\Models\AdvanceBalance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MealEntry;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Support\ExpenseKind;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D-07: ~50-member reproducible demo/perf fixture.
 *
 * Produces a realistic monthly dataset for one mess:
 *   - 48 active members + 1 former + 1 inactive (exercises denominator + proration)
 *   - A full month of B/L/D meal entries through "today"
 *   - 2-3 bazar purchases per day
 *   - One of each fixed-expense category for the month
 *   - A mix of bill_payment + advance_deposit payments
 *   - Demo creds: manager@demo.test + member@demo.test (both password: "password")
 *
 * Guarded: DatabaseSeeder does NOT call this. Run explicitly via:
 *   php artisan db:seed --class=PerfDemoSeeder
 * OR the env-guarded `php artisan db:seed:perf-demo` command. NEVER in production.
 */
class PerfDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Pitfall 6 — disable audit during seed (owen-it/auditing respects this).
        config(['audit.enabled' => false]);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ([
            'payments',
            'expenses',
            'meal_entries',
            'meal_off_requests',
            'guest_meals',
            'advance_balances',
            'members',
            'monthly_closings',
            'monthly_member_summaries',
            'monthly_corrections',
            'expense_categories',
            'messes',
            'user_roles',
            'users',
        ] as $table) {
            DB::table($table)->truncate();
        }

        // Ensure the three Tyro roles exist (idempotent) before we assign them below.
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        Role::firstOrCreate(['slug' => 'mess-member'], ['name' => 'Mess Member']);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Demo mess + manager + member logins (D-16 README demo creds source).
        $mess = Mess::factory()->create(['name' => 'Demo Mess']);
        // Force Mess::activeId() to pick up the demo mess (warms the cache).
        Mess::forgetActiveIdCache();
        Mess::find($mess->id);

        // Seed expense categories for the demo mess (the seeder iterates Mess::all()).
        (new ExpenseCategorySeeder)->run();

        $manager = User::factory()->create([
            'name' => 'Demo Manager',
            'email' => 'manager@demo.test',
            'password' => bcrypt('password'),
        ]);
        $manager->assignRole(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $demoMemberUser = User::factory()->create([
            'name' => 'Demo Member',
            'email' => 'member@demo.test',
            'password' => bcrypt('password'),
        ]);
        $demoMemberUser->assignRole(Role::firstOrCreate(['slug' => 'mess-member'], ['name' => 'Mess Member']));

        // 2. ~50 members (48 active + 1 former + 1 inactive).
        $members = Member::factory()->count(48)->for($mess)->create();
        $formerMember = Member::factory()->former()->for($mess)->create();
        $inactiveMember = Member::factory()->inactive()->for($mess)->create();

        // Link the demo member user to the first member record.
        $members->first()->update(['user_id' => $demoMemberUser->id]);

        // 3. Advance balances for every active + former member.
        foreach ($members as $m) {
            AdvanceBalance::factory()->for($mess)->for($m)->create();
        }
        AdvanceBalance::factory()->for($mess)->for($formerMember)->create();

        // 4. A full month of meals (B/L/D) through "today" for active + former members.
        $today = now(); // Asia/Dhaka after Plan 05-01 Task 1.
        $mealMembers = $members->merge([$formerMember]);
        foreach ($mealMembers as $member) {
            for ($d = 1; $d <= $today->day; $d++) {
                MealEntry::factory()
                    ->for($mess)
                    ->for($member)
                    ->create([
                        'date' => $today->copy()->setDay($d)->toDateString(),
                        'breakfast' => rand(0, 1) === 1,
                        'lunch' => true, // lunch is near-universal
                        'dinner' => rand(0, 1) === 1,
                    ]);
            }
        }

        // 5. Bazar (kind=bazar via the expense_category) — 3-5 entries per day through "today".
        // Density tuned so even mid-month runs clear the >=60 threshold regardless of day-of-month.
        $bazarCategories = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->pluck('id');
        for ($d = 1; $d <= $today->day; $d++) {
            $entriesToday = rand(3, 5);
            for ($i = 0; $i < $entriesToday; $i++) {
                Expense::factory()
                    ->for($mess)
                    ->create([
                        'date' => $today->copy()->setDay($d)->toDateString(),
                        'purchased_by' => $members->random()->id,
                        'expense_category_id' => $bazarCategories->random(),
                        'amount' => rand(300, 1500) + (rand(0, 99) / 100),
                    ]);
            }
        }

        // 6. Fixed expenses (kind=fixed via the expense_category) — one of each fixed category for the month.
        $fixedCategories = ExpenseCategory::where('kind', ExpenseKind::FIXED)->pluck('id');
        foreach ($fixedCategories as $catId) {
            Expense::factory()
                ->for($mess)
                ->create([
                    'date' => $today->copy()->startOfMonth()->toDateString(),
                    'expense_category_id' => $catId,
                    'amount' => rand(500, 5000) + (rand(0, 99) / 100),
                ]);
        }

        // 7. Payments — bill_payment for ~half the members, advance_deposit for ~30% of those.
        $payingMembers = $members->random((int) ceil($members->count() / 2));
        foreach ($payingMembers as $m) {
            Payment::factory()
                ->for($mess)
                ->for($m)
                ->create([
                    'date' => $today->copy()->subDays(rand(0, 10))->toDateString(),
                    'type' => PaymentType::BILL_PAYMENT,
                    'method' => collect(PaymentMethod::ALL)->random(),
                    'amount' => rand(1000, 3000) + (rand(0, 99) / 100),
                ]);

            if (rand(0, 9) < 3) {
                Payment::factory()
                    ->for($mess)
                    ->for($m)
                    ->create([
                        'date' => $today->copy()->subDays(rand(0, 10))->toDateString(),
                        'type' => PaymentType::ADVANCE_DEPOSIT,
                        'method' => collect(PaymentMethod::ALL)->random(),
                        'amount' => rand(500, 2000) + (rand(0, 99) / 100),
                    ]);
            }
        }

        // 8. Clear caches so the perf measurement starts cold.
        Cache::flush();
        // inactive member is intentionally excluded from meals/advance (no meal_entries, no advance_balance).
    }
}
