@extends('layouts.pdf')

@section('title', __('Expense Report'))

@section('report-body')
    @php
        use App\Support\Money;
        use App\Support\ExpenseKind;

        $total = collect($rows)->sum(fn ($e) => (float) $e->amount);
        $fromLabel = $filters['from'] ?? null;
        $toLabel = $filters['to'] ?? null;
        $categoryLabel = isset($filters['category_id']) && $filters['category_id']
            ? (collect($categories)->firstWhere('id', (int) $filters['category_id'])->name ?? '')
            : __('All');
    @endphp

    <div class="label">
        @if ($fromLabel || $toLabel)
            {{ __('From') }}: {{ $fromLabel ?? '?' }} — {{ __('To') }}: {{ $toLabel ?? '?' }}
        @else
            {{ __('All dates') }}
        @endif
        | {{ __('Category') }}: {{ $categoryLabel }}
    </div>

    <div class="totals">{{ __('Total') }}: {{ Money::taka($total) }}</div>

    @if (! empty($rows))
        <table>
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Vendor') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $expense)
                    <tr>
                        <td>{{ $expense->date ? $expense->date->format('Y-m-d') : '' }}</td>
                        <td>{{ $expense->category?->name ?? '—' }}</td>
                        <td>{{ $expense->description ?? '—' }}</td>
                        <td>{{ $expense->vendor ?? '—' }}</td>
                        <td class="num">{{ Money::taka($expense->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="label">{{ __('No expenses match the current filters.') }}</div>
    @endif
@endsection
