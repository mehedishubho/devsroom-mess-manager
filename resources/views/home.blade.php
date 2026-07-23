@extends('layouts.app')
@section('content')
    @php
        use App\Models\MonthlyClosing;
        use App\Support\Money;
        use Carbon\Carbon;

        $now = Carbon::now();
        $currentClosing = MonthlyClosing::query()
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->first();
        $cards = $cards ?? [
            'total_members' => 0, 'today_meals' => 0.0,
            'monthly_expenses' => 0.0, 'meal_rate' => 0.0,
            'total_member_balance' => 0.0,
        ];
        $pendingMealOff = $pendingMealOff ?? 0;
        $charts = $charts ?? ['meal' => ['labels' => [], 'values' => []], 'expense' => ['labels' => [], 'values' => []], 'payment' => ['labels' => [], 'values' => []]];

        $hasChartData =
            ! empty($charts['meal']['labels']) ||
            ! empty($charts['expense']['labels']) ||
            ! empty($charts['payment']['labels']);
    @endphp

    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">
            {{ __('Dashboard') }}
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}
        </p>
    </header>

    @if ($currentClosing)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <div>
                <p class="font-semibold">{{ __('MONTH CLOSED — :label is locked.', ['label' => $now->format('F Y')]) }}</p>
                <p class="mt-1 text-amber-800">{{ __('Meal/expense/payment writes for this month are disabled. Use corrections to adjust a closed month.') }}</p>
            </div>
            <a href="{{ route('mess.closings.show', $currentClosing) }}" class="btn btn-sm border border-amber-300 bg-amber-50 text-amber-800 shadow-sm hover:bg-amber-100">
                {{ __('View closing') }}
            </a>
        </div>
    @endif

    {{-- DASH-03: Pending meal-off alert banner (rendered only when count > 0) --}}
    @if ($pendingMealOff > 0)
        <a href="{{ route('mess.meal-off.index') }}" class="mb-4 block rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 transition-colors hover:bg-amber-100">
            {{ trans_choice(':count pending meal off request awaiting approval|:count pending meal off requests awaiting approval', $pendingMealOff) }}
        </a>
    @endif

    {{-- DASH-01: 6 stat cards --}}
    <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
        <x-stat-card
            :label="__('Total Members')"
            :value="(string) number_format((int) $cards['total_members'])" />

        <x-stat-card
            :label="__('Today\'s Meals')"
            :value="number_format((float) $cards['today_meals'], 1)" />

        @php
            $mealRateHint = ((float) $cards['meal_rate'] === 0.0) ? __('no bazar recorded yet') : null;
        @endphp
        <x-stat-card
            :label="__('Current Meal Rate')"
            :value="Money::taka((float) $cards['meal_rate']).' / '.__('meal')"
            :hint="$mealRateHint" />

        <x-stat-card
            :label="__('Monthly Expenses')"
            :value="Money::taka((float) $cards['monthly_expenses'])" />

        @php
            $netBalance = (float) ($cards['total_member_balance'] ?? 0);
            $netValue = ($netBalance < 0 ? __('Owes').' ' : ($netBalance > 0 ? __('Credit').' ' : '')).Money::taka(abs($netBalance));
            $netHint = $netBalance < 0 ? __('Members net owe the mess') : ($netBalance > 0 ? __('Members net in credit') : __('Settled'));
        @endphp
        <x-stat-card
            :label="__('Member balances (net)')"
            :value="$netValue"
            :hint="$netHint" />
    </section>

    {{-- DASH-02: 3 charts (D-27 empty state when no data anywhere) --}}
    @if (! $hasChartData)
        <x-empty-state
            :title="__('No data yet')"
            :description="__('Charts appear once you have expenses, meals, or payments.')" />
    @else
        <section class="grid grid-cols-1 gap-4">
            {{-- Meal Trend (line) — D-05 --}}
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Meal Trend') }}</h3>
                    <form method="GET" action="{{ route('home') }}" class="flex flex-wrap items-center gap-1">
                        <input type="hidden" name="expense_from" value="{{ $charts['expense']['range']['from'] ?? '' }}">
                        <input type="hidden" name="expense_to" value="{{ $charts['expense']['range']['to'] ?? '' }}">
                        <input type="hidden" name="payment_from" value="{{ $charts['payment']['range']['from'] ?? '' }}">
                        <input type="hidden" name="payment_to" value="{{ $charts['payment']['range']['to'] ?? '' }}">
                        <label class="text-xs text-slate-600">{{ __('From') }}
                            <input type="date" name="meal_from" value="{{ $charts['meal']['range']['from'] ?? '' }}" class="input input-date mt-1 w-auto max-w-44">
                        </label>
                        <label class="text-xs text-slate-600">{{ __('To') }}
                            <input type="date" name="meal_to" value="{{ $charts['meal']['range']['to'] ?? '' }}" class="input input-date mt-1 w-auto max-w-44">
                        </label>
                        <button type="submit" class="btn btn-dark btn-sm">{{ __('Apply') }}</button>
                    </form>
                </div>
                <div style="height: 280px;">
                    <canvas id="meal-trend-chart"></canvas>
                </div>
            </div>

            {{-- Expense Trend (bar, bazar-only) — D-06 --}}
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Expense Trend') }}</h3>
                    <form method="GET" action="{{ route('home') }}" class="flex flex-wrap items-center gap-1">
                        <input type="hidden" name="meal_from" value="{{ $charts['meal']['range']['from'] ?? '' }}">
                        <input type="hidden" name="meal_to" value="{{ $charts['meal']['range']['to'] ?? '' }}">
                        <input type="hidden" name="payment_from" value="{{ $charts['payment']['range']['from'] ?? '' }}">
                        <input type="hidden" name="payment_to" value="{{ $charts['payment']['range']['to'] ?? '' }}">
                        <label class="text-xs text-slate-600">{{ __('From') }}
                            <input type="date" name="expense_from" value="{{ $charts['expense']['range']['from'] ?? '' }}" class="input input-date mt-1 w-auto max-w-44">
                        </label>
                        <label class="text-xs text-slate-600">{{ __('To') }}
                            <input type="date" name="expense_to" value="{{ $charts['expense']['range']['to'] ?? '' }}" class="input input-date mt-1 w-auto max-w-44">
                        </label>
                        <button type="submit" class="btn btn-dark btn-sm">{{ __('Apply') }}</button>
                    </form>
                </div>
                <div style="height: 280px;">
                    <canvas id="expense-trend-chart"></canvas>
                </div>
            </div>

            {{-- Payment Trend (bar, all methods) — D-07 --}}
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Payment Trend') }}</h3>
                    <form method="GET" action="{{ route('home') }}" class="flex flex-wrap items-center gap-1">
                        <input type="hidden" name="meal_from" value="{{ $charts['meal']['range']['from'] ?? '' }}">
                        <input type="hidden" name="meal_to" value="{{ $charts['meal']['range']['to'] ?? '' }}">
                        <input type="hidden" name="expense_from" value="{{ $charts['expense']['range']['from'] ?? '' }}">
                        <input type="hidden" name="expense_to" value="{{ $charts['expense']['range']['to'] ?? '' }}">
                        <label class="text-xs text-slate-600">{{ __('From') }}
                            <input type="date" name="payment_from" value="{{ $charts['payment']['range']['from'] ?? '' }}" class="input input-date mt-1 w-auto max-w-44">
                        </label>
                        <label class="text-xs text-slate-600">{{ __('To') }}
                            <input type="date" name="payment_to" value="{{ $charts['payment']['range']['to'] ?? '' }}" class="input input-date mt-1 w-auto max-w-44">
                        </label>
                        <button type="submit" class="btn btn-dark btn-sm">{{ __('Apply') }}</button>
                    </form>
                </div>
                <div style="height: 280px;">
                    <canvas id="payment-trend-chart"></canvas>
                </div>
            </div>
        </section>
    @endif

    {{-- Chart.js init (uses the Plan 4.0 global helper — destroy-before-recreate guard). --}}
    @once
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                window.initDashboardChart('meal-trend-chart', {
                    type: 'line',
                    data: {
                        labels: @json($charts['meal']['labels']),
                        datasets: [{
                            label: '@lang('Meals')',
                            data: @json($charts['meal']['values']),
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5,150,105,0.1)',
                            tension: 0.3,
                            fill: true,
                        }],
                    },
                });
                window.initDashboardChart('expense-trend-chart', {
                    type: 'bar',
                    data: {
                        labels: @json($charts['expense']['labels']),
                        datasets: [{
                            label: '@lang('Bazar')',
                            data: @json($charts['expense']['values']),
                            backgroundColor: '#059669',
                        }],
                    },
                });
                window.initDashboardChart('payment-trend-chart', {
                    type: 'bar',
                    data: {
                        labels: @json($charts['payment']['labels']),
                        datasets: [{
                            label: '@lang('Collected')',
                            data: @json($charts['payment']['values']),
                            backgroundColor: '#0ea5e9',
                        }],
                    },
                });
            });
        </script>
    @endonce
@endsection
