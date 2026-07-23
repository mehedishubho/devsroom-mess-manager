<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * MemberStatementExport — RPT-08 Excel variant of a single member's statement.
 *
 * Source: `MemberStatementService::forMember()` array shape. We emit one
 * sheet with a Section column ('Meal' | 'Payment') so the manager can
 * filter/aggregate each section independently. The 'Detail' column carries
 * either the per-day B/L/D markers or the payment method.
 *
 * Amount column (D) is raw number with FORMAT_NUMBER_00 (Pitfall 5).
 */
class MemberStatementExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping
{
    public function __construct(
        private readonly array $statement,
    ) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->statement['daily'] ?? [] as $d) {
            $rows->push([
                'section' => __('Meal'),
                'date' => $d['date'] ?? '',
                'detail' => sprintf('B:%d L:%d D:%d', $d['breakfast'] ? 1 : 0, $d['lunch'] ? 1 : 0, $d['dinner'] ? 1 : 0),
                'amount' => (float) ($d['meal_value'] ?? 0),
            ]);
        }

        foreach ($this->statement['payments'] ?? [] as $p) {
            $rows->push([
                'section' => __('Payment'),
                'date' => $p->date ? $p->date->toDateString() : '',
                'detail' => (string) ($p->method ?? ''),
                'amount' => (float) ($p->amount ?? 0),
            ]);
        }

        // Closing net position (credit − debt) as a single summary row.
        $row = $this->statement['row'] ?? [];
        if (! empty($row)) {
            $rows->push([
                'section' => __('Balance'),
                'date' => '',
                'detail' => __('Carried forward'),
                'amount' => (float) (($row['advance_balance'] ?? 0) - ($row['due_balance'] ?? 0)),
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            __('Section'),
            __('Date'),
            __('Detail'),
            __('Amount'),
        ];
    }

    /**
     * @param  array{section:string,date:string,detail:string,amount:float}  $row
     */
    public function map($row): array
    {
        return [
            $row['section'],
            $row['date'],
            $row['detail'],
            (float) $row['amount'],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
