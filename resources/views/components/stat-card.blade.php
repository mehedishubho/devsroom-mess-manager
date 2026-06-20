@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
])

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600" aria-hidden="true">{{ $icon }}</span>
        @endif
    </div>
    <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
