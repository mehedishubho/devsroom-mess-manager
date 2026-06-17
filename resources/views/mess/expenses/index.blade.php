@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Expenses') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('All bazar and fixed expenses, most recent first.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mess.expenses.bazar.create') }}" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                {{ __('Add bazar') }}
            </a>
            <a href="{{ route('mess.expenses.fixed.create') }}" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                {{ __('Add fixed') }}
            </a>
        </div>
    </header>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Date') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Kind') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Category') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Description') }}</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $expense->date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <x-status-pill :variant="$expense->category?->kind ?? 'bazar'" />
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-900">{{ $expense->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $expense->description ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-slate-900">{{ number_format((float) $expense->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-600">
                            {{ __('No expenses recorded yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>
@endsection
