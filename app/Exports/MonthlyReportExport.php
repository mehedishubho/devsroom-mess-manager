<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * MonthlyReportExport — RPT-08 Excel variant of the Monthly Report.
 *
 * Source: `ReportService::monthlyReport()` array shape (which mirrors
 * BillPreviewService::preview() for live months + MonthlyMemberSummary
 * for closed months). Each row is a single member's bill summary.
 *
 * Money columns (B-G) are raw numbers with FORMAT_NUMBER_00 so the
 * manager can SUM/AVERAGE in Excel (Pitfall 5 mitigation). map() returns
 * explicit (float) casts.
 *
 * D-19 structural enforcement: MyReportExportController::monthlyExcel
 * passes a modified array with `members` emptied — this class happily
 * emits a header-only sheet in that case (no peer rows leak).
 */
class MonthlyReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings
{
    public function __construct(
        private readonly array $preview,
    ) {}

    public function array(): array
    {
        $members = $this->preview['members'] ?? [];

        return array_map(fn ($m) => [
            $m['name'] ?? '',
            (float) ($m['meals'] ?? 0),
            (float) ($m['meal_cost'] ?? 0),
            (float) ($m['fixed_share'] ?? 0),
            (float) ($m['bill'] ?? 0),
            (float) ($m['bill_payments'] ?? 0),
            (float) ($m['due'] ?? 0),
            (float) (($m['advance_balance'] ?? 0) - ($m['due_balance'] ?? 0)),
        ], $members);
    }

    public function headings(): array
    {
        return [
            __('Member'),
            __('Meals'),
            __('Meal Cost'),
            __('Fixed'),
            __('Bill'),
            __('Paid'),
            __('Due'),
            __('Balance'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_00,
            'C' => NumberFormat::FORMAT_NUMBER_00,
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
