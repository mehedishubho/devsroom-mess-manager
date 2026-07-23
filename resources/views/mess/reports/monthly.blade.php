@extends('layouts.app')
@section('content')
    @php
        use App\Support\Money;
        use Carbon\Carbon;

        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');
        $isSnapshot = ($data['source'] ?? 'live') === 'snapshot';
        $members = $data['members'] ?? [];
        $totalDue = collect($members)->sum('due');
        $totalNet = collect($members)->sum(fn ($r) => ($r['advance_balance'] ?? 0) - ($r['due_balance'] ?? 0));
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

    <div class="mb-6">
        <x-report-toolbar route="mess.reports.monthly" :year="$year" :month="$month" showExports="true" :from="$monthRange['first'] ?? null" :to="$monthRange['last'] ?? null" :filters="request()->query('from') || request()->query('to') || request()->query('category_id') || request()->query('month') ? request()->only(['from', 'to', 'category_id', 'month']) : []" />
    </div>

    @if (! $hasData)
        <x-empty-state
            :title="__('No data for :month yet', ['month' => $period])"
            :description="__('Once meals, bazar, or fixed expenses are entered for this month, the report will appear here.')" />
    @else
        {{-- Totals grid --}}
        <section class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Members') }}</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ count($members) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meals') }}</p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ number_format((float) $data['total_meals'], 1) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meal rate') }}</p>
                @php
                    $totalBazar = (float) ($data['total_bazar'] ?? 0.0);
                    $mealRateHint = ((float) $data['meal_rate'] === 0.0)
                        ? ($totalBazar > 0.0 ? __('bazar recorded, but no meals yet') : __('no bazar recorded yet'))
                        : null;
                @endphp
                @if ($mealRateHint)
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ Money::taka(0) }} <span class="text-sm font-normal text-slate-500">/ {{ __('meal') }}</span></p>
                    <p class="mt-1 text-xs text-slate-500">{{ $mealRateHint }}</p>
                @else
                    <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ Money::taka($data['meal_rate']) }} <span class="text-sm font-normal text-slate-500">/ {{ __('meal') }}</span></p>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total bazar') }}</p>
                <p class="mt-2 text-xl font-bold tracking-tight text-slate-900">{{ Money::taka($data['total_bazar']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total fixed') }}</p>
                <p class="mt-2 text-xl font-bold tracking-tight text-slate-900">{{ Money::taka($data['total_fixed']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Balance (net)') }}</p>
                <p class="mt-2 text-2xl font-bold tracking-tight {{ $totalNet < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                    {{ $totalNet < 0 ? __('Owes') : __('Credit') }} {{ Money::taka(abs($totalNet)) }}
                </p>
            </div>
        </section>

        {{-- Per-member table --}}
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Member') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meals') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Meal cost') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Fixed') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Guest') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Bill') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Paid') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Due') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($members as $row)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    <a href="{{ route('mess.reports.member-statement', ['member_id' => $row['member_id'], 'year' => $year, 'month' => $month]) }}"
                                       class="text-emerald-700 hover:underline">
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClasses = match ($row['status'] ?? 'active') {
                                            'former' => 'bg-slate-100 text-slate-700',
                                            'inactive' => 'bg-rose-100 text-rose-700',
                                            default => 'bg-emerald-100 text-emerald-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full {{ $statusClasses }} px-2 py-0.5 text-xs font-medium">
                                        {{ __(ucfirst($row['status'] ?? 'active')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $row['meals'], 1) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ Money::taka($row['meal_cost']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ Money::taka($row['fixed_share']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ Money::taka($row['guest_total']) }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ Money::taka($row['bill']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ Money::taka($row['bill_payments']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-rose-600">{{ Money::taka($row['due']) }}</td>
                                @php $rowNet = ($row['advance_balance'] ?? 0) - ($row['due_balance'] ?? 0); @endphp
                                <td class="px-4 py-3 text-right tabular-nums font-medium {{ $rowNet < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ $rowNet < 0 ? __('Owes') : __('Credit') }} {{ Money::taka(abs($rowNet)) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
