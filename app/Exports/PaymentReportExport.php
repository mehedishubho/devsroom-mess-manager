<?php

namespace App\Exports;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * PaymentReportExport — RPT-08 Excel variant of the Payment Report.
 *
 * Uses FromQuery so Maatwebsite chunks the result (T-04-03-03). Filters
 * are validated by PaymentReportRequest.
 *
 * Amount column (E) is raw number with FORMAT_NUMBER_00 (Pitfall 5).
 */
class PaymentReportExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $messId,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?int $memberId = null,
        private readonly ?string $method = null,
    ) {}

    /**
     * @return Builder<Payment>
     */
    public function query()
    {
        return Payment::query()
            ->where('mess_id', $this->messId)
            ->with(['member:id,name'])
            ->when($this->from, fn ($q, $d) => $q->where('date', '>=', $d))
            ->when($this->to, fn ($q, $d) => $q->where('date', '<=', $d))
            ->when($this->memberId, fn ($q, $id) => $q->where('member_id', $id))
            ->when($this->method, fn ($q, $m) => $q->where('method', $m))
            ->orderBy('date');
    }

    /**
     * @param  Payment  $row
     */
    public function map($row): array
    {
        return [
            $row->date ? $row->date->format('Y-m-d') : '',
            $row->member?->name ?? '',
            (string) ($row->method ?? ''),
            (string) ($row->type ?? ''),
            (float) $row->amount,
            (string) ($row->reference ?? ''),
        ];
    }

    public function headings(): array
    {
        return [
            __('Date'),
            __('Member'),
            __('Method'),
            __('Type'),
            __('Amount'),
            __('Reference'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
