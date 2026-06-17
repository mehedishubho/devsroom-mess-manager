<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
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

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        if (! $this->service->delete($category)) {
            return redirect()
                ->route('mess.categories.index')
                ->with('error', __('Default categories cannot be deleted.'));
        }

        return redirect()
            ->route('mess.categories.index')
            ->with('success', __('Category removed.'));
    }
}
