@extends('layouts.app')
@section('content')
    @php use App\Support\Money; @endphp

    <header class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Expense') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $expense->date->format('d M Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mess.expenses.edit', $expense) }}" class="btn btn-primary">{{ __('Edit') }}</a>
            <form method="POST" action="{{ route('mess.expenses.destroy', $expense) }}" onsubmit="return confirm('{{ __('Remove this expense?') }}');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost text-rose-700">{{ __('Delete') }}</button>
            </form>
            <a href="{{ route('mess.expenses.index') }}" class="btn btn-ghost">{{ __('Back') }}</a>
        </div>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-slate-500">{{ __('Category') }}</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ $expense->category?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Kind') }}</dt>
                <dd class="mt-1 text-slate-900">{{ __(ucfirst((string) ($expense->category?->kind ?? ''))) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Amount') }}</dt>
                <dd class="mt-1 text-lg font-bold text-slate-900">{{ Money::taka($expense->amount) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Vendor') }}</dt>
                <dd class="mt-1 text-slate-900">{{ $expense->vendor ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Purchased by') }}</dt>
                <dd class="mt-1 text-slate-900">{{ $expense->purchasedByMember?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Entered by') }}</dt>
                <dd class="mt-1 text-slate-900">{{ $expense->enteredBy?->name ?? '—' }}</dd>
            </div>
        </dl>

        @if ($expense->description)
            <div class="mt-4 border-t border-slate-200 pt-4">
                <dt class="text-xs text-slate-500">{{ __('Description') }}</dt>
                <dd class="mt-1 text-slate-900">{{ $expense->description }}</dd>
            </div>
        @endif

        @if ($expense->receipt_path)
            <div class="mt-4 border-t border-slate-200 pt-4">
                <p class="text-xs text-slate-500">{{ __('Receipt') }}</p>
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($expense->receipt_path) }}" target="_blank" rel="noopener" class="mt-1 inline-block text-emerald-700 hover:underline">{{ __('View receipt') }}</a>
            </div>
        @endif
    </div>
@endsection
