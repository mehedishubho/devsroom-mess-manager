@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
])

<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between gap-2">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="text-slate-400" aria-hidden="true">{{ $icon }}</span>
        @endif
    </div>
    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
