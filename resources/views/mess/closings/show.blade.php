@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Closing :label', ['label' => \Carbon\Carbon::create($closing->year, $closing->month, 1)->format('F Y')]) }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Closed at :when by :who', ['when' => $closing->closed_at?->format('d-m-Y H:i'), 'who' => $closing->closedBy?->name ?? '—']) }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('mess.closings.corrections.create', $closing) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Add correction') }}</a>
            <a href="{{ route('mess.closings.corrections.index', $closing) }}" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">{{ __('View corrections') }}</a>
        </div>
    </header>
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Total bazar') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ \App\Support\Money::taka($closing->total_bazar) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Total fixed') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ \App\Support\Money::taka($closing->total_fixed_expense) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Meal rate') }}</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ \App\Support\Money::taka($closing->meal_rate) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Members') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $closing->member_count }}</p>
        </div>
    </div>
    <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
        {{ __('MONTH CLOSED — :label. This view is read-only. Corrections only via /mess/closings/:id/corrections.', ['label' => \Carbon\Carbon::create($closing->year, $closing->month, 1)->format('F Y'), 'id' => $closing->id]) }}
    </div>
    @include('mess.closings._member-summaries')
@endsection
