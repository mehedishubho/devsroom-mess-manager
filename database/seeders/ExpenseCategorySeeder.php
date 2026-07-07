<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Mess;
use App\Support\ExpenseKind;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        // These 6 defaults replace the original 13 generic categories.
        // The migration 2026_07_06_000001_update_default_expense_categories.php
        // handles the transition for existing installations; the seeder ensures
        // fresh installations (or re-seeded environments) get the right defaults.
        $defaults = [
            // FIXED
            ['name' => 'Electricity Bill', 'kind' => ExpenseKind::FIXED, 'sort_order' => 1],
            ['name' => 'Bua Bill',         'kind' => ExpenseKind::FIXED, 'sort_order' => 2],
            ['name' => 'Gas Bill',         'kind' => ExpenseKind::FIXED, 'sort_order' => 3],
            ['name' => 'Dust Bill',        'kind' => ExpenseKind::FIXED, 'sort_order' => 4],
            ['name' => 'Rent',             'kind' => ExpenseKind::FIXED, 'sort_order' => 5],
            // BAZAR
            ['name' => 'Others',           'kind' => ExpenseKind::BAZAR, 'sort_order' => 99],
        ];

        Mess::all()->each(function (Mess $mess) use ($defaults) {
            foreach ($defaults as $cat) {
                ExpenseCategory::firstOrCreate(
                    [
                        'mess_id' => $mess->id,
                        'slug' => Str::slug($cat['name']),
                    ],
                    [
                        'name' => $cat['name'],
                        'kind' => $cat['kind'],
                        'is_default' => true,
                        'sort_order' => $cat['sort_order'],
                    ]
                );
            }
        });
    }
}
