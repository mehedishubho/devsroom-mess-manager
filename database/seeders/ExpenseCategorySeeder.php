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
        $defaults = [
            ['name' => 'Vegetables', 'kind' => ExpenseKind::BAZAR, 'sort_order' => 1],
            ['name' => 'Fish', 'kind' => ExpenseKind::BAZAR, 'sort_order' => 2],
            ['name' => 'Meat', 'kind' => ExpenseKind::BAZAR, 'sort_order' => 3],
            ['name' => 'Rice', 'kind' => ExpenseKind::BAZAR, 'sort_order' => 4],
            ['name' => 'Spices', 'kind' => ExpenseKind::BAZAR, 'sort_order' => 5],
            ['name' => 'Gas', 'kind' => ExpenseKind::BAZAR, 'sort_order' => 6],
            ['name' => 'Other bazar', 'kind' => ExpenseKind::BAZAR, 'sort_order' => 99],
            ['name' => 'House rent', 'kind' => ExpenseKind::FIXED, 'sort_order' => 1],
            ['name' => 'Utility (electricity)', 'kind' => ExpenseKind::FIXED, 'sort_order' => 2],
            ['name' => 'Utility (water)', 'kind' => ExpenseKind::FIXED, 'sort_order' => 3],
            ['name' => 'Internet', 'kind' => ExpenseKind::FIXED, 'sort_order' => 4],
            ['name' => 'Cook', 'kind' => ExpenseKind::FIXED, 'sort_order' => 5],
            ['name' => 'Other fixed', 'kind' => ExpenseKind::FIXED, 'sort_order' => 99],
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
