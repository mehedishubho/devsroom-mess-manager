@extends('layouts.app')
@section('content')
    @php
        use App\Support\Money;
        use App\Support\ExpenseKind;
    @endphp

    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Expense Report') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('Total') }}: <span class="font-semibold text-slate-900">{{ Money::taka($report['totals']['amount']) }}</span>
        </p>
    </header>

    @include('mess.reports._filters.expenses', ['categories' => $categories])

    @if ($report['rows']->isEmpty())
        <x-empty-state
            :title="__('No expenses match the current filters.')"
            :description="__('Try widening the date range, choosing a different category, or clearing filters.')" />
    @else
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Date') }}</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Category') }}</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Description') }}</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Vendor') }}</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Purchased by') }}</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report['rows'] as $expense)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-slate-900">{{ $expense->date->format('d-m-Y') }}</td>
                                <td class="px-3 py-2 text-slate-700">
                                    @if ($expense->category)
                                        {{ $expense->category->name }}
                                        @if ($expense->category->kind === ExpenseKind::BAZAR)
                                            <span class="ml-1 text-xs text-emerald-700">({{ __('bazar') }})</span>
                                        @elseif ($expense->category->kind === ExpenseKind::FIXED)
                                            <span class="ml-1 text-xs text-slate-500">({{ __('fixed') }})</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-slate-900">{{ $expense->description ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $expense->vendor ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $expense->purchasedByMember?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium text-slate-900">{{ Money::taka($expense->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4">
            {{ $report['rows']->links() }}
        </div>
    @endif
@endsection
