@props(['method', 'color' => 'slate'])
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full bg-{$color}-100 px-2 py-0.5 text-xs font-medium text-{$color}-800"]) }}>
    {{ \App\Support\PaymentMethod::LABELS[$method] ?? $method }}
</span>