<header class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('My bill preview') }}</h2>
    <p class="text-sm text-slate-600">{{ __('Estimate for the selected month — refreshes within 1 hour.') }}</p>
</header>
@if (! $row)
    <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
        {{ __('No data for this month yet.') }}
    </p>
@else
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Meals') }}</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($row['meals'], 2) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Meal cost') }}</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Support\Money::taka($row['meal_cost']) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Fixed share') }}</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Support\Money::taka($row['fixed_share']) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Guest meals') }}</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Support\Money::taka($row['guest_total']) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 sm:col-span-2">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Bill') }}</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">{{ \App\Support\Money::taka($row['bill']) }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-xs uppercase tracking-wide text-emerald-700">{{ __('Paid so far') }}</div>
            <div class="mt-1 text-xl font-semibold text-emerald-700">{{ \App\Support\Money::taka($row['bill_payments']) }}</div>
        </div>
        <div class="rounded-lg border {{ $row['due'] > 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
            <div class="text-xs uppercase tracking-wide {{ $row['due'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $row['due'] > 0 ? __('You owe') : __('Credit') }}</div>
            <div class="mt-1 text-xl font-semibold {{ $row['due'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ \App\Support\Money::taka(abs($row['due'])) }}</div>
        </div>
    </div>
@endif