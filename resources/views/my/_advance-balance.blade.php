<header class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('My balance') }}</h2>
    <p class="text-sm text-slate-600">{{ __('Your current balance with the mess.') }}</p>
</header>
@php
    $bal = $member->advanceBalance;
    $net = $bal?->netBalance() ?? 0;
    $isCredit = $net > 0;
    $isOwes = $net < 0;
@endphp
<div class="rounded-lg border p-4 {{ $isOwes ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }}">
    <div class="text-xs uppercase tracking-wide {{ $isOwes ? 'text-rose-700' : 'text-emerald-700' }}">
        {{ $isOwes ? __('You owe') : __('Credit') }}
    </div>
    <div class="mt-1 text-2xl font-semibold {{ $isOwes ? 'text-rose-700' : 'text-emerald-700' }}">
        {{ \App\Support\Money::taka(abs($net)) }}
    </div>
    <p class="mt-1 text-xs {{ $isOwes ? 'text-rose-700' : 'text-emerald-700' }}">
        @if ($isOwes)
            {{ __('Pay this to settle your account.') }}
        @elseif ($isCredit)
            {{ __('Carried forward as credit on your next bill.') }}
        @else
            {{ __('Your account is settled.') }}
        @endif
    </p>
</div>
@if ($bal?->last_updated_at)
    <p class="mt-3 text-xs text-slate-500">{{ __('Last updated') }}: {{ $bal->last_updated_at->format('d-m-Y H:i') }}</p>
@endif
