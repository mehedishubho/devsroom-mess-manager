<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreExpenseRequest;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Support\ExpenseKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Task 6 (quick-260717-2q3) — unified Add Expense form.
 *
 * Replaces the two split controllers (createBazar/storeBazar/createFixed/
 * storeFixed) with one create() + one store(). The category's kind is the
 * single source of truth — bazar/fixed/other all live behind one entry
 * point and a grouped dropdown.
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
        // Group every category for the active mess by kind, in the canonical
        // ExpenseKind::ALL order so the optgroups stay stable.
        $grouped = ExpenseCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'kind'])
            ->groupBy('kind')
            ->sortKeysUsing(fn ($a, $b) => array_search($a, ExpenseKind::ALL) <=> array_search($b, ExpenseKind::ALL));

        return view('mess.expenses.create', compact('grouped'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->service->create($request->validated(), $request->file('receipt'));

        return redirect()
            ->route('mess.expenses.index')
            ->with('success', __('Expense of :amount recorded.', ['amount' => number_format((float) $expense->amount, 2)]));
    }
}
