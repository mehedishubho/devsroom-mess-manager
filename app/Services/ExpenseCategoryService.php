<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Models\Mess;
use Illuminate\Support\Str;

class ExpenseCategoryService
{
    public function list(?string $kind = null)
    {
        $query = ExpenseCategory::query()->orderBy('kind')->orderBy('sort_order')->orderBy('name');

        if ($kind) {
            $query->where('kind', $kind);
        }

        return $query->get();
    }

    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::create([
            'mess_id' => Mess::activeId(),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'kind' => $data['kind'],
            'is_default' => false,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public function delete(ExpenseCategory $category): bool
    {
        if ($category->is_default) {
            return false;
        }

        return (bool) $category->delete();
    }
}
