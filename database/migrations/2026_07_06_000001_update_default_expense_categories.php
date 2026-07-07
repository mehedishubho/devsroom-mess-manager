<?php

use App\Models\ExpenseCategory;
use App\Support\ExpenseKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Replace 13 generic default categories with 6 mess-specific ones.
     *
     * Strategy (safe for production data):
     * 1. Delete old defaults that have no expense records — they are unused.
     * 2. Mark old defaults that DO have expenses as non-default (they become
     *    custom categories and keep their FK references intact).
     * 3. Create the 6 new defaults via firstOrCreate — they will not duplicate
     *    existing renamed categories.
     */
    public function up(): void
    {
        $oldDefaults = ExpenseCategory::where('is_default', true)->get();

        foreach ($oldDefaults as $category) {
            $hasExpenses = DB::table('expenses')
                ->where('expense_category_id', $category->id)
                ->exists();

            if ($hasExpenses) {
                // Keep the category (expenses reference it), but stop treating
                // it as a default. The manager can rename/edit it manually.
                $category->update(['is_default' => false]);
            } else {
                // No expenses reference this category — safe to delete.
                $category->delete();
            }
        }

        $newDefaults = [
            // FIXED
            ['name' => 'Electricity Bill', 'kind' => ExpenseKind::FIXED, 'sort_order' => 1],
            ['name' => 'Bua Bill',         'kind' => ExpenseKind::FIXED, 'sort_order' => 2],
            ['name' => 'Gas Bill',         'kind' => ExpenseKind::FIXED, 'sort_order' => 3],
            ['name' => 'Dust Bill',        'kind' => ExpenseKind::FIXED, 'sort_order' => 4],
            ['name' => 'Rent',             'kind' => ExpenseKind::FIXED, 'sort_order' => 5],
            // BAZAR
            ['name' => 'Others',           'kind' => ExpenseKind::BAZAR, 'sort_order' => 99],
        ];

        // Get all mess IDs (active messes or all if no active config)
        $messIds = DB::table('messes')->pluck('id');

        foreach ($messIds as $messId) {
            foreach ($newDefaults as $cat) {
                ExpenseCategory::firstOrCreate(
                    [
                        'mess_id' => $messId,
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
        }
    }

    public function down(): void
    {
        // Cannot reliably reverse — we'd need to know which old defaults were
        // deleted vs kept. This migration is one-way; roll back by re-seeding
        // if needed (php artisan db:seed --class=ExpenseCategorySeeder).
    }
};
