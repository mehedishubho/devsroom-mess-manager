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

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): View
    {
        $expenses = $this->service->list($request);

        return view('mess.expenses.index', compact('expenses'));
    }

    public function createBazar(): View
    {
        $categories = ExpenseCategory::where('kind', ExpenseKind::BAZAR)->orderBy('name')->get();

        return view('mess.expenses.bazar.create', compact('categories'));
    }

    public function storeBazar(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->service->create(
            $request->validated(),
            $request->file('receipt'),
            ExpenseKind::BAZAR,
        );

        return redirect()
            ->route('mess.expenses.index')
            ->with('success', __('Bazar expense of :amount recorded.', ['amount' => number_format((float) $expense->amount, 2)]));
    }

    public function createFixed(): View
    {
        $categories = ExpenseCategory::where('kind', ExpenseKind::FIXED)->orderBy('name')->get();

        return view('mess.expenses.fixed.create', compact('categories'));
    }

    public function storeFixed(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->service->create(
            $request->validated(),
            $request->file('receipt'),
            ExpenseKind::FIXED,
        );

        return redirect()
            ->route('mess.expenses.index')
            ->with('success', __('Fixed expense of :amount recorded.', ['amount' => number_format((float) $expense->amount, 2)]));
    }
}
