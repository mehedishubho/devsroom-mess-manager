@extends('layouts.pdf')

@section('title', __('Monthly Report') . ' — ' . ($period ?? ''))

@section('report-body')
    @php
        use App\Support\Money;
        $members = $data['members'] ?? [];
        $totalDue = collect($members)->sum('due');
        $totalAdvance = collect($members)->sum('advance_balance');
    @endphp

    <div class="totals-grid">
        <div><span class="label">{{ __('Members') }}:</span> {{ count($members) }}</div>
        <div><span class="label">{{ __('Meals') }}:</span> {{ number_format((float) ($data['total_meals'] ?? 0), 1) }}</div>
        <div><span class="label">{{ __('Meal rate') }}:</span> {{ Money::taka($data['meal_rate'] ?? 0) }} / {{ __('meal') }}</div>
        <div><span class="label">{{ __('Total bazar') }}:</span> {{ Money::taka($data['total_bazar'] ?? 0) }}</div>
        <div><span class="label">{{ __('Total fixed') }}:</span> {{ Money::taka($data['total_fixed'] ?? 0) }}</div>
        @php $pdfNet = collect($members)->sum(fn ($r) => ($r['advance_balance'] ?? 0) - ($r['due_balance'] ?? 0)); @endphp
        <div><span class="label">{{ __('Balance (net)') }}:</span> {{ ($pdfNet < 0 ? __('Owes') : __('Credit')).' '.Money::taka(abs($pdfNet)) }}</div>
    </div>

    @if (! empty($members))
        {{-- D-13: column compaction via pdf-table-compact --}}
        <table class="pdf-table-compact">
            <thead>
                <tr>
                    <th>{{ __('Member') }}</th>
                    <th class="num">{{ __('Meals') }}</th>
                    <th class="num">{{ __('Meal cost') }}</th>
                    <th class="num">{{ __('Fixed') }}</th>
                    <th class="num">{{ __('Bill') }}</th>
                    <th class="num">{{ __('Paid') }}</th>
                    <th class="num">{{ __('Due') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="num">{{ number_format((float) $row['meals'], 1) }}</td>
                        <td class="num">{{ Money::taka($row['meal_cost']) }}</td>
                        <td class="num">{{ Money::taka($row['fixed_share']) }}</td>
                        <td class="num">{{ Money::taka($row['bill']) }}</td>
                        <td class="num">{{ Money::taka($row['bill_payments']) }}</td>
                        <td class="num">{{ Money::taka($row['due']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
