@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">
            {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('Quick access to today\'s mess operations.') }}
        </p>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('mess.members.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:bg-slate-50 md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Members') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Add, edit, and search mess members.') }}</p>
            <p class="mt-3 text-2xl font-semibold text-emerald-700">{{ \App\Models\Member::where('status', 'active')->count() }}</p>
            <p class="text-xs text-slate-500">{{ __('Active members') }}</p>
        </a>

        <a href="{{ route('mess.settings.edit') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:bg-slate-50 md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Mess settings') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Update name, address, rent, meal values, and currency.') }}</p>
        </a>

        <a href="{{ route('mess.audit') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:bg-slate-50 md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Audit log') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Every change to mess data, recorded with the user, timestamp, and before/after values.') }}</p>
        </a>

        <a href="{{ route('mess.payments.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:bg-slate-50 md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Payments') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Record a bill payment or advance deposit.') }}</p>
            <p class="mt-3 text-2xl font-semibold text-emerald-700">{{ \App\Models\Payment::count() }}</p>
            <p class="text-xs text-slate-500">{{ __('Total payments recorded') }}</p>
        </a>

        <a href="{{ route('mess.advance-balances.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:bg-slate-50 md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Advance balances') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('View and adjust member credit and debt.') }}</p>
        </a>
    </div>
@endsection
