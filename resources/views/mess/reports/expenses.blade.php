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
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Category') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Description') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Vendor') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Purchased by') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report['rows'] as $expense)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-900">{{ $expense->date->format('d-m-Y') }}</td>
                                <td class="px-4 py-3 text-slate-700">
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
                                <td class="px-4 py-3 text-slate-900">{{ $expense->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->vendor ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->purchasedByMember?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-900">{{ Money::taka($expense->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <th colspan="5" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total') }}</th>
                            <th class="px-4 py-3 text-right tabular-nums font-bold text-slate-900">{{ Money::taka($report['totals']['amount']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="mt-4">
            {{ $report['rows']->links() }}
        </div>
    @endif
@endsection
