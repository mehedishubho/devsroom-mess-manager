@extends('layouts.app')
@section('content')
    @php
        use App\Support\Money;
        use App\Support\PaymentMethod;
        use App\Support\PaymentType;
    @endphp

    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Payment Report') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('Total collected') }}: <span class="font-semibold text-slate-900">{{ Money::taka($report['totals']['amount']) }}</span>
        </p>
    </header>

    @include('mess.reports._filters.payments', ['members' => $members])

    @if ($report['rows']->isEmpty())
        <x-empty-state
            :title="__('No payments match the current filters.')"
            :description="__('Try a different member, method, or date range.')" />
    @else
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Member') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Method') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Type') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Reference') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report['rows'] as $payment)
                            @php
                                $methodColor = PaymentMethod::COLORS[$payment->method] ?? 'slate';
                            @endphp
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-900">{{ $payment->date->format('d-m-Y') }}</td>
                                <td class="px-4 py-3 text-slate-900">{{ $payment->member?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-{{ $methodColor }}-100 px-2 py-0.5 text-xs font-medium text-{{ $methodColor }}-800">
                                        {{ __(PaymentMethod::LABELS[$payment->method] ?? ucfirst((string) $payment->method)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ __(PaymentType::LABELS[$payment->type] ?? ucfirst((string) $payment->type)) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-900">{{ Money::taka($payment->amount) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $payment->reference ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <th colspan="4" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total collected') }}</th>
                            <th class="px-4 py-3 text-right tabular-nums font-bold text-slate-900">{{ Money::taka($report['totals']['amount']) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="mt-4">
            {{ $report['rows']->links() }}
        </div>
    @endif
@endsection
