<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Models\Mess;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    /**
     * Rename a non-default category. Slug is regenerated from the new name;
     * uniqueness is enforced per-mess (NOT global — two messes may have the
     * same slug). Default categories are locked from rename (operational
     * invariant — the seeded defaults drive the kind-detection logic).
     *
     * @param  array{name: string, sort_order?: int}  $data
     *
     * @throws ValidationException When the category is_default OR the regenerated
     *                             slug collides with another category in this mess.
     */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        if ($category->is_default) {
            throw ValidationException::withMessages([
                'name' => __('Default categories cannot be renamed.'),
            ]);
        }

        $newSlug = Str::slug($data['name']);
        $collision = ExpenseCategory::query()
            ->where('mess_id', $category->mess_id)
            ->where('slug', $newSlug)
            ->where('id', '!=', $category->id)
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'name' => __('A category with this name already exists.'),
            ]);
        }

        $category->slug = $newSlug;
        $category->name = $data['name'];
        if (array_key_exists('sort_order', $data)) {
            $category->sort_order = (int) $data['sort_order'];
        }
        $category->save();

        return $category;
    }

    /**
     * Delete a category. Default categories are never deletable (locked).
     * Categories with linked expenses are refused — orphaning financial
     * history is worse than forcing the manager to reassign first.
     *
     * @return bool True on successful delete; false when default OR has expenses.
     */
    public function delete(ExpenseCategory $category): bool
    {
        if ($category->is_default) {
            return false;
        }

        if ($category->expenses()->exists()) {
            return false;
        }

        return (bool) $category->delete();
    }

    /**
     * Distinguish the two delete-refusal reasons so the controller can flash
     * the right message. Default-category refusal is structural (the seeded
     * defaults); has-expenses refusal is operational (the manager must
     * reassign before deleting).
     */
    public function cannotDeleteReason(ExpenseCategory $category): ?string
    {
        if ($category->is_default) {
            return 'default';
        }

        if ($category->expenses()->exists()) {
            return 'has_expenses';
        }

        return null;
    }
}
