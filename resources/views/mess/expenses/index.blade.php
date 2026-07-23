@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Expenses') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('All bazar and fixed expenses, most recent first.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mess.expenses.create') }}" class="btn btn-primary">
                {{ __('Add expense') }}
            </a>
        </div>
    </header>

    {{-- Mobile cards (touch-friendly summary) --}}
    <div class="space-y-3 md:hidden">
        @forelse ($expenses as $expense)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">{{ $expense->date->format('d M Y') }}</span>
                    <x-status-pill :variant="$expense->category?->kind ?? 'bazar'" />
                </div>
                <div class="mt-1 font-medium text-slate-900">{{ $expense->category?->name ?? '—' }}</div>
                @if ($expense->description)
                    <div class="mt-0.5 truncate text-sm text-slate-600">{{ $expense->description }}</div>
                @endif
                @if ($expense->vendor)
                    <div class="mt-0.5 truncate text-xs text-slate-500">{{ $expense->vendor }}</div>
                @endif
                <div class="mt-2 flex items-center justify-between">
                    <div class="flex gap-2">
                        <a href="{{ route('mess.expenses.show', $expense) }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('View') }}</a>
                        <a href="{{ route('mess.expenses.edit', $expense) }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('Edit') }}</a>
                    </div>
                    <span class="text-sm font-semibold text-slate-900">{{ number_format((float) $expense->amount, 2) }}</span>
                </div>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
                {{ __('No expenses recorded yet.') }}
            </p>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Date') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Kind') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Category') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Description') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Vendor') }}</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Amount') }}</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
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
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $expense->vendor ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-slate-900">{{ number_format((float) $expense->amount, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('mess.expenses.show', $expense) }}" class="btn btn-sm btn-ghost">{{ __('View') }}</a>
                                <a href="{{ route('mess.expenses.edit', $expense) }}" class="btn btn-sm btn-ghost">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('mess.expenses.destroy', $expense) }}" onsubmit="return confirm('{{ __('Remove this expense?') }}');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost text-rose-700">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-600">
                            {{ __('No expenses recorded yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>
@endsection
