@extends('layouts.pdf')

@section('title', __('My Statement') . ' — ' . ($member->name ?? ''))

@section('report-body')
    @php
        use App\Support\Money;
        use App\Support\PaymentType;

        $row = $statement['row'] ?? [];
        $meals = (float) ($row['meals'] ?? 0.0);
        $mealCost = (float) ($row['meal_cost'] ?? 0.0);
        $mealRate = $meals > 0 ? ($mealCost / $meals) : 0.0;
        $daily = $statement['daily'] ?? [];
        $billPayments = collect($statement['payments'])->filter(fn ($p) => $p->type === PaymentType::BILL_PAYMENT);
        $advanceDeposits = collect($statement['payments'])->filter(fn ($p) => $p->type === PaymentType::ADVANCE_DEPOSIT);
        $guestTotal = collect($statement['guests'])->sum('charge_amount');
    @endphp

    <h2 style="margin-top: 0;">{{ $member->name ?? '' }}</h2>
    <div class="label">{{ $statement['period_label'] ?? '' }}
        @if (($statement['source'] ?? 'live') === 'snapshot') — {{ __('Closed month') }}@endif
    </div>

    {{-- D-25: Meal-rate math --}}
    <div class="math-line">
        {{ __('Meal rate') }}: {{ Money::taka($mealRate) }} / {{ __('meal') }}
        × {{ number_format($meals, 1) }} {{ __('meals') }}
        = {{ Money::taka($mealCost) }}
    </div>

    {{-- Daily meals --}}
    <div class="section">
        <h2>{{ __('Daily meals') }}</h2>
        @if (empty($daily))
            <div class="label">{{ __('No meal entries for this month.') }}</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>B</th><th>L</th><th>D</th>
                        <th class="num">{{ __('Meal value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daily as $d)
                        <tr>
                            <td>{{ $d['date'] }}</td>
                            <td>{{ $d['breakfast'] ? 'Y' : '—' }}</td>
                            <td>{{ $d['lunch'] ? 'Y' : '—' }}</td>
                            <td>{{ $d['dinner'] ? 'Y' : '—' }}</td>
                            <td class="num">{{ number_format((float) $d['meal_value'], 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (! empty($statement['guests']) && count($statement['guests']) > 0)
        <div class="section">
            <h2>{{ __('Guest meals') }} — {{ Money::taka($guestTotal) }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Guest') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statement['guests'] as $g)
                        <tr>
                            <td>{{ $g->date ? $g->date->format('Y-m-d') : '' }}</td>
                            <td>{{ $g->guest_name }}</td>
                            <td class="num">{{ Money::taka($g->charge_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Payments --}}
    <div class="section">
        <h2>{{ __('Bill payments') }}</h2>
        @if ($billPayments->isEmpty())
            <div class="label">{{ __('No bill payments this month.') }}</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($billPayments as $p)
                        <tr>
                            <td>{{ $p->date ? $p->date->format('Y-m-d') : '' }}</td>
                            <td>{{ ucfirst((string) $p->method) }}</td>
                            <td>{{ $p->reference ?? '—' }}</td>
                            <td class="num">{{ Money::taka($p->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($advanceDeposits->isNotEmpty())
        <div class="section">
            <h2>{{ __('Advance deposits') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($advanceDeposits as $p)
                        <tr>
                            <td>{{ $p->date ? $p->date->format('Y-m-d') : '' }}</td>
                            <td>{{ ucfirst((string) $p->method) }}</td>
                            <td>{{ $p->reference ?? '—' }}</td>
                            <td class="num">{{ Money::taka($p->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Closing summary --}}
    <div class="section totals">
        <h2>{{ __('Closing summary') }}</h2>
        <div><span class="label">{{ __('Fixed share') }}:</span> {{ Money::taka($row['fixed_share'] ?? 0) }}</div>
        <div><span class="label">{{ __('Meal cost') }}:</span> {{ Money::taka($row['meal_cost'] ?? 0) }}</div>
        <div><span class="label">{{ __('Bill') }}:</span> {{ Money::taka($row['bill'] ?? 0) }}</div>
        <div><span class="label">{{ __('Paid') }}:</span> {{ Money::taka($row['bill_payments'] ?? 0) }}</div>
        <div><span class="label">{{ __('Closing due') }}:</span> {{ Money::taka($row['due'] ?? 0) }}</div>
        <div><span class="label">{{ __('Advance balance') }}:</span> {{ Money::taka($row['advance_balance'] ?? 0) }}</div>
    </div>
@endsection
