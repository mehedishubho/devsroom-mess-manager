<?php

namespace App\Exports;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * ExpenseReportExport — RPT-08 Excel variant of the Expense Report.
 *
 * Uses FromQuery (not FromCollection) so Maatwebsite chunks the result
 * automatically — avoids memory exhaustion on large datasets (T-04-03-03).
 * Filters are validated upstream by ExpenseReportRequest.
 *
 * Amount column (E) is raw number with FORMAT_NUMBER_00 (Pitfall 5).
 */
class ExpenseReportExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $messId,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?int $categoryId = null,
        private readonly ?string $month = null,
    ) {}

    /**
     * @return Builder<Expense>
     */
    public function query()
    {
        return Expense::query()
            ->where('mess_id', $this->messId)
            ->with(['category:id,name,kind', 'purchasedByMember:id,name'])
            ->when($this->from, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($this->to, fn ($q, $d) => $q->where('date', '<=', $d))
            ->when($this->categoryId, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($this->month, function ($q, $m) {
                [$y, $mo] = array_map('intval', explode('-', (string) $m));
                $q->whereYear('date', $y)->whereMonth('date', $mo);
            })
            ->orderBy('date');
    }

    /**
     * @param  Expense  $row
     */
    public function map($row): array
    {
        return [
            $row->date ? $row->date->format('Y-m-d') : '',
            $row->category?->name ?? '',
            (string) ($row->description ?? ''),
            (string) ($row->vendor ?? ''),
            (float) $row->amount,
        ];
    }

    public function headings(): array
    {
        return [
            __('Date'),
            __('Category'),
            __('Description'),
            __('Vendor'),
            __('Amount'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
