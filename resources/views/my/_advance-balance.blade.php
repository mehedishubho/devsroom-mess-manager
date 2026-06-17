<header class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('My advance balance') }}</h2>
    <p class="text-sm text-slate-600">{{ __('Your current credit and debt with the mess.') }}</p>
</header>
@php
    $bal = $member->advanceBalance;
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
        <div class="text-xs uppercase tracking-wide text-emerald-700">{{ __('Advance (credit)') }}</div>
        <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ \App\Support\Money::taka($bal?->balance ?? 0) }}</div>
        <p class="mt-1 text-xs text-emerald-700">{{ __('Carried forward as credit on your next month bill.') }}</p>
    </div>
    <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
        <div class="text-xs uppercase tracking-wide text-rose-700">{{ __('Due (debt)') }}</div>
        <div class="mt-1 text-2xl font-semibold text-rose-700">{{ \App\Support\Money::taka($bal?->due_balance ?? 0) }}</div>
        <p class="mt-1 text-xs text-rose-700">{{ __('You owe this to the mess.') }}</p>
    </div>
</div>
@if ($bal?->last_updated_at)
    <p class="mt-3 text-xs text-slate-500">{{ __('Last updated') }}: {{ $bal->last_updated_at->format('d-m-Y H:i') }}</p>
@endif