<div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Total bazar') }}</div>
        <div class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Support\Money::taka($preview['total_bazar']) }}</div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Total meals') }}</div>
        <div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($preview['total_meals'], 2) }}</div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Meal rate') }}</div>
        <div class="mt-1 text-xl font-semibold text-emerald-700">{{ \App\Support\Money::taka($preview['meal_rate']) }}</div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ __('Total fixed') }}</div>
        <div class="mt-1 text-xl font-semibold text-slate-900">{{ \App\Support\Money::taka($preview['total_fixed']) }}</div>
    </div>
</div>