@props(['variant', 'label' => null])

@php
    $styles = match ($variant) {
        'active', 'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'inactive', 'pending' => 'bg-slate-100 text-slate-600 border-slate-200',
        'former', 'rejected' => 'bg-red-50 text-red-700 border-red-200',
        'bazar' => 'bg-amber-50 text-amber-800 border-amber-200',
        'fixed' => 'bg-sky-50 text-sky-800 border-sky-200',
        default => 'bg-slate-100 text-slate-600 border-slate-200',
    };
    $displayLabel = $label ?? match ($variant) {
        'active' => __('Active'),
        'inactive' => __('Inactive'),
        'former' => __('Former'),
        'pending' => __('Pending'),
        'approved' => __('Approved'),
        'rejected' => __('Rejected'),
        'bazar' => __('Bazar'),
        'fixed' => __('Fixed'),
        default => ucfirst($variant),
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium $styles"]) }} aria-label="{{ __('Status: :label', ['label' => $displayLabel]) }}">
    {{ $displayLabel }}
</span>
