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

    {{-- DASH-01: stat cards --}}
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

    {{-- Report widgets (replaces the old 3 trend charts) --}}
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Members with dues --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Members with dues') }}</h3>
            @if (empty($membersWithDues))
                <p class="text-sm text-slate-500">{{ __('No one currently owes the mess. 🎉') }}</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($membersWithDues as $m)
                        <li class="flex items-center justify-between py-2">
                            <a href="{{ route('mess.members.wallet', $m['id']) }}" class="text-sm font-medium text-slate-900 hover:text-emerald-700 hover:underline">{{ $m['name'] }}</a>
                            <span class="text-sm font-semibold text-rose-700">{{ Money::taka(abs($m['net'])) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Top eaters --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Top eaters this month') }}</h3>
            @if (empty($topEaters))
                <p class="text-sm text-slate-500">{{ __('No meals recorded yet this month.') }}</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($topEaters as $m)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-sm font-medium text-slate-900">{{ $m['name'] }}</span>
                            <span class="text-sm text-slate-600">{{ number_format((float) $m['meals'], 1) }} {{ __('meals') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Bazar vs collection (bar) --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Spend vs collection this month') }}</h3>
            <div style="height: 240px;">
                <canvas id="bazar-collection-chart"></canvas>
            </div>
        </div>

        {{-- Expense category mix (doughnut) --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Expense categories this month') }}</h3>
            @if (empty($expenseCategoryMix))
                <p class="text-sm text-slate-500">{{ __('No expenses recorded yet this month.') }}</p>
            @else
                <div style="height: 240px;">
                    <canvas id="expense-category-chart"></canvas>
                </div>
            @endif
        </div>
    </section>

    @once
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                window.initDashboardChart('bazar-collection-chart', {
                    type: 'bar',
                    data: {
                        labels: [@json([__('Spend'), __('Collected')])],
                        datasets: [{
                            label: '@lang('Amount')',
                            data: [@json([(float) ($bazarVsCollection['spend'] ?? 0), (float) ($bazarVsCollection['collected'] ?? 0)])],
                            backgroundColor: ['#f43f5e', '#059669'],
                        }],
                    },
                });
                @if (! empty($expenseCategoryMix))
                    window.initDashboardChart('expense-category-chart', {
                        type: 'doughnut',
                        data: {
                            labels: @json(collect($expenseCategoryMix)->pluck('label')),
                            datasets: [{
                                data: @json(collect($expenseCategoryMix)->pluck('amount')),
                                backgroundColor: ['#059669', '#0ea5e9', '#f59e0b', '#8b5cf6', '#f43f5e', '#64748b', '#10b981', '#ec4899'],
                            }],
                        },
                    });
                @endif
            });
        </script>
    @endonce
@endsection
