@php
    use App\Support\Money;

    $overview = $overview ?? null;
@endphp

@if (! $overview || $overview['member'] === null)
    <x-empty-state
        :title="__('Your mess account is not set up.')"
        :description="__('Please ask the manager to finish linking your account.')" />
@else
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            :label="__('My Meals (this month)')"
            :value="number_format((float) $overview['my_meals'], 1)"
            :hint="__('Total meal value')" />

        <x-stat-card
            :label="__('My Bill (this month)')"
            :value="Money::taka((float) $overview['my_bill'])"
            :hint="__('As of today')" />

        @php
            $myBalance = (float) ($overview['my_balance'] ?? 0);
        @endphp
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('My balance') }}</p>
                <a href="{{ route('my.wallet') }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('View wallet') }}</a>
            </div>
            <p class="mt-2 text-lg font-bold {{ $myBalance < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                {{ $myBalance < 0 ? __('Owes') : ($myBalance > 0 ? __('Credit') : '') }} {{ Money::taka(abs($myBalance)) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ $myBalance < 0 ? __('You owe the mess') : ($myBalance > 0 ? __('Credit with the mess') : __('Settled')) }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('My Payment History') }}</p>
                <a href="{{ route('my.payments') }}" class="text-xs font-medium text-emerald-700 hover:underline">
                    {{ __('View all') }}
                </a>
            </div>
            @if ($overview['recent_payments']->isEmpty())
                <p class="mt-2 text-sm text-slate-500">{{ __('No payments yet.') }}</p>
            @else
                <ul class="mt-2 divide-y divide-slate-100">
                    @foreach ($overview['recent_payments'] as $payment)
                        <li class="flex items-center justify-between py-2">
                            <div class="text-sm">
                                <p class="font-medium text-slate-900">{{ Money::taka((float) $payment->amount) }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $payment->date instanceof \Illuminate\Support\Carbon ? $payment->date->format('d-m-Y') : $payment->date }}
                                </p>
                            </div>
                            <x-method-pill :method="$payment->method" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
