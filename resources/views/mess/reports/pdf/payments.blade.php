@extends('layouts.pdf')

@section('title', __('Payment Report'))

@section('report-body')
    @php
        use App\Support\Money;

        $total = collect($rows)->sum(fn ($p) => (float) $p->amount);
        $fromLabel = $filters['from'] ?? null;
        $toLabel = $filters['to'] ?? null;
        $memberLabel = isset($filters['member_id']) && $filters['member_id']
            ? (collect($members)->firstWhere('id', (int) $filters['member_id'])->name ?? '')
            : __('All');
        $methodLabel = isset($filters['method']) && $filters['method']
            ? ucfirst((string) $filters['method'])
            : __('All');
    @endphp

    <div class="label">
        @if ($fromLabel || $toLabel)
            {{ __('From') }}: {{ $fromLabel ?? '?' }} — {{ __('To') }}: {{ $toLabel ?? '?' }}
        @else
            {{ __('All dates') }}
        @endif
        | {{ __('Member') }}: {{ $memberLabel }} | {{ __('Method') }}: {{ $methodLabel }}
    </div>

    <div class="totals">{{ __('Total collected') }}: {{ Money::taka($total) }}</div>

    @if (! empty($rows))
        <table>
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Member') }}</th>
                    <th>{{ __('Method') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                    <th>{{ __('Reference') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $payment)
                    <tr>
                        <td>{{ $payment->date ? $payment->date->format('Y-m-d') : '' }}</td>
                        <td>{{ $payment->member?->name ?? '—' }}</td>
                        <td>{{ ucfirst((string) $payment->method) }}</td>
                        <td>{{ ucfirst((string) $payment->type) }}</td>
                        <td class="num">{{ Money::taka($payment->amount) }}</td>
                        <td>{{ $payment->reference ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="label">{{ __('No payments match the current filters.') }}</div>
    @endif
@endsection
