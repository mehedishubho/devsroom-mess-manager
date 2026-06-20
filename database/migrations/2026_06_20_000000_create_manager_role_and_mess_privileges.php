<?php

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;

/**
 * Adds the "Manager" role (full mess-management authority, on par with the
 * existing `admin` role) and a documented set of `mess.*` privileges attached to
 * the admin, super-admin, and manager roles.
 *
 * This migration is purely additive and idempotent (updateOrCreate / sync) — it
 * does NOT touch users, existing roles, or existing privileges, and never wipes
 * data. Run with `php artisan migrate` (never `migrate:fresh`).
 */
return new class extends Migration
{
    /**
     * Mess-management privilege definitions. The `roles` list declares which
     * roles each privilege is attached to. Enforcement in this app is
     * role-based (route middleware + FormRequest authorize()), so these
     * privilege records document the Manager's capabilities and make them
     * queryable via Tyro (e.g. `php artisan tyro:list-roles-with-privileges`).
     */
    private const MESS_PRIVILEGES = [
        [
            'name' => 'Manage Mess',
            'slug' => 'mess.manage',
            'description' => 'Manage mess settings, members, and invitations.',
            'roles' => ['admin', 'super-admin', 'manager'],
        ],
        [
            'name' => 'Manage Meals',
            'slug' => 'meals.manage',
            'description' => 'Manage meal entries, guest meals, and meal-off approvals.',
            'roles' => ['admin', 'super-admin', 'manager'],
        ],
        [
            'name' => 'Manage Expenses',
            'slug' => 'expenses.manage',
            'description' => 'Manage expenses and expense categories.',
            'roles' => ['admin', 'super-admin', 'manager'],
        ],
        [
            'name' => 'Manage Payments',
            'slug' => 'payments.manage',
            'description' => 'Manage payments and member advance balances.',
            'roles' => ['admin', 'super-admin', 'manager'],
        ],
        [
            'name' => 'Close Month',
            'slug' => 'month.close',
            'description' => 'Trigger month close and post monthly corrections.',
            'roles' => ['admin', 'super-admin', 'manager'],
        ],
        [
            'name' => 'View Reports',
            'slug' => 'reports.view',
            'description' => 'View mess reports and bill preview.',
            'roles' => ['admin', 'super-admin', 'manager'],
        ],
    ];

    public function up(): void
    {
        // 1. The Manager role.
        Role::updateOrCreate(
            ['slug' => 'manager'],
            ['name' => 'Manager']
        );

        // 2. Attach each mess privilege to its declared roles.
        $roleMap = $this->roleMap();

        foreach (self::MESS_PRIVILEGES as $definition) {
            $privilege = Privilege::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ]
            );

            $roleIds = collect($definition['roles'])
                ->map(fn (string $slug) => $roleMap->get($slug)?->id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($roleIds)) {
                $privilege->roles()->sync($roleIds);
            }
        }
    }

    public function down(): void
    {
        // Remove only the mess privileges introduced here (and their pivot rows).
        // The `manager` role is intentionally left in place so any user still
        // assigned to it is not orphaned in `user_roles`.
        $slugs = array_column(self::MESS_PRIVILEGES, 'slug');

        $privileges = Privilege::query()->whereIn('slug', $slugs)->get();

        foreach ($privileges as $privilege) {
            $privilege->roles()->detach();
            $privilege->delete();
        }
    }

    /**
     * @return Collection<string, Role>  roles keyed by slug.
     */
    private function roleMap(): Collection
    {
        $slugs = collect(self::MESS_PRIVILEGES)
            ->flatMap(fn (array $definition) => $definition['roles'])
            ->unique()
            ->values()
            ->all();

        return Role::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');
    }
};
