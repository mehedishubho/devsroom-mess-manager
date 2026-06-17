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
        $dailyTotalB = 0;
        $dailyTotalL = 0;
        $dailyTotalD = 0;
        $dailyTotalValue = 0.0;
        foreach ($daily as $d) {
            if ($d['breakfast']) { $dailyTotalB++; }
            if ($d['lunch']) { $dailyTotalL++; }
            if ($d['dinner']) { $dailyTotalD++; }
            $dailyTotalValue += $d['meal_value'];
        }
        $guestTotal = collect($statement['guests'])->sum('charge_amount');
    @endphp

    <header class="mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ $member->name }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ __('My Statement') }} — {{ $statement['period_label'] }}
                    @if ($isSnapshot)
                        <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                            {{ __('Closed month') }}
                        </span>
                    @endif
                </p>
            </div>
        </div>
    </header>

    {{-- Month picker only — NO member picker (member is fixed = self) --}}
    <div class="mb-6">
        <x-report-toolbar route="my.reports.statement" :year="$year" :month="$month" showExports="true" />
    </div>

    @if (empty($row))
        <x-empty-state
            :title="__('No statement available for :period', ['period' => $statement['period_label']])"
            :description="__('You were not active during this month, or no data was recorded.')" />
    @else
        {{-- Meal-rate math (D-25) --}}
        <section class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-emerald-900">{{ __('Meal rate') }}</h2>
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-slate-900">
                <span class="text-lg font-semibold">{{ Money::taka($mealRate) }}</span>
                <span class="text-sm text-slate-600">/ {{ __('meal') }}</span>
                <span class="text-slate-400">×</span>
                <span class="text-lg font-semibold">{{ number_format($meals, 1) }}</span>
                <span class="text-sm text-slate-600">{{ __('meals') }}</span>
                <span class="text-slate-400">=</span>
                <span class="text-lg font-semibold text-emerald-700">{{ Money::taka($mealCost) }}</span>
            </div>
        </section>

        {{-- Daily meal breakdown (D-23) --}}
        <section class="mb-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('Daily meals') }}</h2>
            </div>
            @if (empty($daily))
                <p class="px-4 py-6 text-sm text-slate-500">{{ __('No meal entries for this month.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Date') }}</th>
                                <th class="px-3 py-2 text-center font-semibold text-slate-700">B</th>
                                <th class="px-3 py-2 text-center font-semibold text-slate-700">L</th>
                                <th class="px-3 py-2 text-center font-semibold text-slate-700">D</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-700">{{ __('Meal value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($daily as $d)
                                <tr>
                                    <td class="px-3 py-2 text-slate-900">{{ Carbon::parse($d['date'])->format('d-m-Y') }}</td>
                                    <td class="px-3 py-2 text-center">{{ $d['breakfast'] ? '✓' : '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $d['lunch'] ? '✓' : '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $d['dinner'] ? '✓' : '—' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($d['meal_value'], 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td class="px-3 py-2 text-slate-900">{{ __('Totals') }}</td>
                                <td class="px-3 py-2 text-center tabular-nums">{{ $dailyTotalB }}</td>
                                <td class="px-3 py-2 text-center tabular-nums">{{ $dailyTotalL }}</td>
                                <td class="px-3 py-2 text-center tabular-nums">{{ $dailyTotalD }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($dailyTotalValue, 1) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>

        {{-- Guest meals --}}
        @if ($statement['guests']->isNotEmpty())
            <section class="mb-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('Guest meals') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Date') }}</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Guest') }}</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Meal') }}</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-700">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($statement['guests'] as $g)
                                <tr>
                                    <td class="px-3 py-2 text-slate-900">{{ $g->date->format('d-m-Y') }}</td>
                                    <td class="px-3 py-2 text-slate-900">{{ $g->guest_name }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ __(ucfirst((string) $g->meal_type)) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ Money::taka($g->charge_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right text-slate-700">{{ __('Total') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ Money::taka($guestTotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        @endif

        {{-- Payments: bill payments + advance deposits --}}
        <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('Bill payments') }}</h2>
                </div>
                @if ($billPayments->isEmpty())
                    <p class="px-4 py-4 text-sm text-slate-500">{{ __('No bill payments this month.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Date') }}</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Method') }}</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Reference') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-700">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($billPayments as $p)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-900">{{ $p->date->format('d-m-Y') }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ __(ucfirst((string) $p->method)) }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $p->reference ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ Money::taka($p->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('Advance deposits') }}</h2>
                </div>
                @if ($advanceDeposits->isEmpty())
                    <p class="px-4 py-4 text-sm text-slate-500">{{ __('No advance deposits this month.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Date') }}</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Method') }}</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-700">{{ __('Reference') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-700">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($advanceDeposits as $p)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-900">{{ $p->date->format('d-m-Y') }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ __(ucfirst((string) $p->method)) }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ $p->reference ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ Money::taka($p->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        {{-- Closing summary card --}}
        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('Closing summary') }}</h2>
            <dl class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Opening advance') }}</dt>
                    <dd class="text-base font-semibold text-emerald-700">{{ Money::taka($row['advance_balance'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Opening due') }}</dt>
                    <dd class="text-base font-semibold text-rose-700">{{ Money::taka($row['due_balance'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Fixed share') }}</dt>
                    <dd class="text-base font-semibold text-slate-900">{{ Money::taka($row['fixed_share'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Meal cost') }}</dt>
                    <dd class="text-base font-semibold text-slate-900">{{ Money::taka($row['meal_cost'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Bill') }}</dt>
                    <dd class="text-base font-semibold text-slate-900">{{ Money::taka($row['bill'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Paid') }}</dt>
                    <dd class="text-base font-semibold text-slate-900">{{ Money::taka($row['bill_payments'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Closing due') }}</dt>
                    <dd class="text-base font-semibold text-rose-700">{{ Money::taka($row['due'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('Advance balance') }}</dt>
                    <dd class="text-base font-semibold text-emerald-700">{{ Money::taka($row['advance_balance'] ?? 0) }}</dd>
                </div>
            </dl>
        </section>
    @endif
@endsection
