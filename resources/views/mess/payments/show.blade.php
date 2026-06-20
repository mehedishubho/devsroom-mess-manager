@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Payment') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $payment->date->format('d-m-Y') }} · {{ $payment->member?->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mess.payments.index') }}" class="btn btn-secondary">{{ __('Back') }}</a>
            <a href="{{ route('mess.payments.edit', $payment) }}" class="btn btn-primary">{{ __('Edit') }}</a>
        </div>
    </header>
    <dl class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-6">
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Member') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $payment->member?->name ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Date') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $payment->date->format('d-m-Y') }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Type') }}</dt><dd class="mt-1"><x-payment-type-pill :type="$payment->type" /></dd></div>
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Method') }}</dt><dd class="mt-1"><x-method-pill :method="$payment->method" /></dd></div>
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Amount') }}</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ \App\Support\Money::taka($payment->amount) }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Reference') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $payment->reference ?? '—' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Notes') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $payment->notes ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Entered by') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $payment->enteredBy?->name ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wide text-slate-500">{{ __('Created') }}</dt><dd class="mt-1 text-sm text-slate-900">{{ $payment->created_at->format('d-m-Y H:i') }}</dd></div>
    </dl>
@endsection