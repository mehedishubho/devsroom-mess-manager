<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreCategoryRequest;
use App\Http\Requests\Mess\UpdateCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $service) {}

    public function index(): View
    {
        $categories = $this->service->list();

        return view('mess.categories.index', compact('categories'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('mess.categories.index')
            ->with('success', __('Category added.'));
    }

    public function edit(ExpenseCategory $category): View
    {
        // Defense-in-depth: the index view only shows the Edit link on
        // non-default categories, but a direct URL hit must also be refused.
        abort_if($category->is_default, 403, __('Default categories cannot be edited.'));

        return view('mess.categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        try {
            $this->service->update($category, $request->validated());
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('mess.categories.index')
            ->with('success', __('Category updated.'));
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        // Surface the specific refusal reason so the manager knows what to do.
        $reason = $this->service->cannotDeleteReason($category);

        if ($reason === 'default') {
            return redirect()
                ->route('mess.categories.index')
                ->with('error', __('Default categories cannot be deleted.'));
        }

        if ($reason === 'has_expenses') {
            return redirect()
                ->route('mess.categories.index')
                ->with('error', __('Cannot delete a category that has expenses; reassign them first.'));
        }

        $this->service->delete($category);

        return redirect()
            ->route('mess.categories.index')
            ->with('success', __('Category removed.'));
    }
}
