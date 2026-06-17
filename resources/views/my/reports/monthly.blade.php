@extends('layouts.app')
@section('content')
    @php
        use App\Support\Money;
        use Carbon\Carbon;

        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');
        $isSnapshot = ($data['source'] ?? 'live') === 'snapshot';
        $members = $data['members'] ?? [];
        $totalDue = collect($members)->sum('due');
        $totalAdvance = collect($members)->sum('advance_balance');
        $hasData = ! empty($members);
    @endphp

    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Monthly Report') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ $period }}
            @if ($isSnapshot)
                <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                    {{ __('Closed month') }}
                </span>
            @endif
        </p>
    </header>

    <div class="mb-6">
        <x-report-toolbar route="my.reports.monthly" :year="$year" :month="$month" showExports="true" />
    </div>

    {{-- Minimal stub — replaced in Task 2 --}}
    @if (! $hasData)
        <x-empty-state
            :title="__('No data for :month yet', ['month' => $period])"
            :description="__('Once meals, bazar, or fixed expenses are entered for this month, the report will appear here.')" />
    @else
        <section class="grid grid-cols-2 gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Meal rate') }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ Money::taka($data['meal_rate']) }} <span class="text-sm font-normal text-slate-500">/ {{ __('meal') }}</span></p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Total bazar') }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ Money::taka($data['total_bazar']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Total fixed') }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ Money::taka($data['total_fixed']) }}</p>
            </div>
        </section>
    @endif
@endsection
