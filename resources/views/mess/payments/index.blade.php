@extends('layouts.app')
@section('content')
    <header class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Payments') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Record and review all mess payments.') }}</p>
        </div>
        <a href="{{ route('mess.payments.create') }}" class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">
            {{ __('Record payment') }}
        </a>
    </header>

    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-4">
        <div>
            <label for="member_id" class="block text-xs font-medium text-slate-600">{{ __('Member') }}</label>
            <select name="member_id" id="member_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach ($members as $id => $name)
                    <option value="{{ $id }}" @selected(($filters['member_id'] ?? null) == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="method" class="block text-xs font-medium text-slate-600">{{ __('Method') }}</label>
            <select name="method" id="method" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach (\App\Support\PaymentMethod::ALL as $m)
                    <option value="{{ $m }}" @selected(($filters['method'] ?? null) === $m)>{{ \App\Support\PaymentMethod::LABELS[$m] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="block text-xs font-medium text-slate-600">{{ __('From') }}</label>
            <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm" />
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-slate-600">{{ __('To') }}</label>
            <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm" />
        </div>
        <div class="flex items-end gap-2 sm:col-span-4">
            <button type="submit" class="inline-flex items-center rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">{{ __('Filter') }}</button>
            <a href="{{ route('mess.payments.index') }}" class="text-sm text-slate-600 hover:text-slate-900">{{ __('Reset') }}</a>
        </div>
    </form>

    @include('mess.payments._list')
@endsection