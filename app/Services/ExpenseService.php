<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Mess;
use App\Support\StorageProvider;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ExpenseService
{
    public function list(Request $request)
    {
        $query = Expense::query()->with(['category', 'purchasedByMember'])->latest('date');

        if ($kind = $request->query('kind')) {
            $query->whereHas('category', fn ($q) => $q->where('kind', $kind));
        }

        return $query->paginate(50)->withQueryString();
    }

    public function create(array $data, ?UploadedFile $receipt = null, string $kind = 'bazar'): Expense
    {
        $expenseData = [
            'mess_id' => Mess::activeId(),
            'expense_category_id' => $data['expense_category_id'],
            'date' => $data['date'],
            'purchased_by' => $data['purchased_by'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'entered_by' => auth()->id(),
        ];

        $expense = Expense::create($expenseData);

        if ($receipt) {
            $ext = $receipt->getClientOriginalExtension();
            $path = "receipts/{$expense->id}.{$ext}";
            // StorageProvider writes the 'public'-disk path verbatim (canonical
            // URL surface) and best-effort mirrors to active cloud disks.
            StorageProvider::store($path, $receipt);
            $expense->update(['receipt_path' => $path]);
        }

        return $expense;
    }
}
