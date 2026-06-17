@extends('layouts.app')
@section('content')
    @php
        use App\Support\Money;
        use App\Support\PaymentType;
        use Carbon\Carbon;

        $row = $statement['row'] ?? [];
        $isSnapshot = ($statement['source'] ?? 'live') === 'snapshot';
        $meals = (float) ($row['meals'] ?? 0.0);
        $mealCost = (float) ($row['meal_cost'] ?? 0.0);
        $mealRate = $meals > 0 ? ($mealCost / $meals) : 0.0;
        $billPayments = $statement['payments']->filter(fn ($p) => $p->type === PaymentType::BILL_PAYMENT);
        $advanceDeposits = $statement['payments']->filter(fn ($p) => $p->type === PaymentType::ADVANCE_DEPOSIT);
        $daily = $statement['daily'] ?? [];
    @endphp

    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ $member->name }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('My Statement') }} — {{ $statement['period_label'] }}
            @if ($isSnapshot)
                <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                    {{ __('Closed month') }}
                </span>
            @endif
        </p>
    </header>

    <div class="mb-6">
        <x-report-toolbar route="my.reports.statement" :year="$year" :month="$month" showExports="true" />
    </div>

    {{-- Minimal stub — replaced in Task 2 --}}
    <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-900">{{ __('Meal rate') }}</h2>
        <p class="mt-2 text-slate-900">
            <span class="text-lg font-semibold">{{ Money::taka($mealRate) }}</span>
            <span class="text-sm text-slate-600">/ {{ __('meal') }}</span>
            <span class="mx-1 text-slate-400">×</span>
            <span class="text-lg font-semibold">{{ number_format($meals, 1) }}</span>
            <span class="text-sm text-slate-600">{{ __('meals') }}</span>
        </p>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('Daily meals') }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ __('Stub — full view in Task 2.') }}</p>
    </section>
@endsection
