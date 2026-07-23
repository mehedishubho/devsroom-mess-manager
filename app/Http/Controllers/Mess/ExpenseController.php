<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreExpenseRequest;
use App\Http\Requests\Mess\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Support\ExpenseKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Unified expense CRUD. One create/store entry point for bazar/fixed/other —
 * the category's kind is the single source of truth. show/edit/update/destroy
 * mirror the Payments resource convention (shared _form partial, separate
 * UpdateExpenseRequest, month.open guard on writes).
 */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): View
    {
        $expenses = $this->service->list($request);

        return view('mess.expenses.index', compact('expenses'));
    }

    public function create(): View
    {
        return view('mess.expenses.create', ['grouped' => $this->groupedCategories()]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->service->create($request->validated(), $request->file('receipt'));

        return redirect()
            ->route('mess.expenses.index')
            ->with('success', __('Expense of :amount recorded.', ['amount' => number_format((float) $expense->amount, 2)]));
    }

    public function show(Expense $expense): View
    {
        $expense->load(['category', 'purchasedByMember', 'enteredBy']);

        return view('mess.expenses.show', compact('expense'));
    }

    public function edit(Expense $expense): View
    {
        return view('mess.expenses.edit', [
            'expense' => $expense,
            'grouped' => $this->groupedCategories(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->service->update($expense, $request->validated(), $request->file('receipt'));

        return redirect()
            ->route('mess.expenses.index')
            ->with('success', __('Expense updated.'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()
            ->route('mess.expenses.index')
            ->with('success', __('Expense removed.'));
    }

    /**
     * Every category for the active mess grouped by kind, in the canonical
     * ExpenseKind::ALL order so the optgroups stay stable across create/edit.
     */
    private function groupedCategories()
    {
        return ExpenseCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'kind'])
            ->groupBy('kind')
            ->sortKeysUsing(fn ($a, $b) => array_search($a, ExpenseKind::ALL) <=> array_search($b, ExpenseKind::ALL));
    }
}
