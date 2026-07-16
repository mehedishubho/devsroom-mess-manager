@extends('layouts.app')
@section('content')
    @php
        use App\Support\Money;
        use Carbon\Carbon;

        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');
        $isSnapshot = ($data['source'] ?? 'live') === 'snapshot';
        // CRITICAL (D-19): $data['members'] is computed by ReportService for
        // aggregate sums only — this view MUST NOT iterate it for per-member
        // display. Only totals derived from it are exposed.
        $members = $data['members'] ?? [];
        $totalDue = collect($members)->sum('due');
        $totalAdvance = collect($members)->sum('advance_balance');
        $hasData = ! empty($members);
    @endphp

    <header class="mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold leading-tight text-slate-900">
                    {{ __('Monthly Report') }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $period }}
                    @if ($isSnapshot)
                        <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                            {{ __('Closed month') }}
                        </span>
                    @endif
                </p>
            </div>
        </div>
    </header>

    {{-- Aggregates-only Monthly Report (D-19): report-toolbar wires the .pdf/.xlsx
         routes which structurally empty the `members` array server-side. --}}
    <div class="mb-6">
        <x-report-toolbar route="my.reports.monthly" :year="$year" :month="$month" showExports="true" :from="$monthRange['first'] ?? null" :to="$monthRange['last'] ?? null" />
    </div>

    @if (! $hasData)
        <x-empty-state
            :title="__('No data for :month yet', ['month' => $period])"
            :description="__('Once meals, bazar, or fixed expenses are entered for this month, the report will appear here.')" />
    @else
        {{-- Aggregates-only totals grid (D-19) — NO per-member table --}}
        <section class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            <x-stat-card
                :label="__('Members')"
                :value="(string) count($members)" />

            <x-stat-card
                :label="__('Meals')"
                :value="number_format((float) $data['total_meals'], 1)" />

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Meal rate') }}</p>
                @if ((float) $data['meal_rate'] === 0.0)
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ Money::taka(0) }} / {{ __('meal') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('no bazar recorded yet') }}</p>
                @else
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ Money::taka($data['meal_rate']) }} <span class="text-sm font-normal text-slate-500">/ {{ __('meal') }}</span></p>
                @endif
            </div>

            <x-stat-card
                :label="__('Total bazar')"
                :value="Money::taka((float) $data['total_bazar'])" />

            <x-stat-card
                :label="__('Total fixed')"
                :value="Money::taka((float) $data['total_fixed'])" />

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Due / Advance') }}</p>
                <p class="mt-1 text-sm font-semibold text-rose-600">{{ Money::taka((float) $totalDue) }}</p>
                <p class="text-sm font-semibold text-emerald-600">{{ Money::taka((float) $totalAdvance) }}</p>
            </div>
        </section>

        <p class="text-xs text-slate-500">
            {{ __('This report shows mess-wide totals. Per-member detail is private — ask the manager for your own statement.') }}
        </p>
    @endif
@endsection
