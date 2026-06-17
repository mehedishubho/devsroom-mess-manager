@props(['tabs', 'activeKey' => ''])

<div role="tablist" class="flex gap-1 overflow-x-auto border-b border-slate-200">
    @foreach ($tabs as $tab)
        @php $isActive = $tab['key'] === $activeKey; @endphp
        <a
            href="{{ $tab['url'] }}"
            role="tab"
            aria-selected="{{ $isActive ? 'true' : 'false' }}"
            aria-current="{{ $isActive ? 'page' : 'false' }}"
            class="flex min-h-[44px] items-center border-b-2 px-3 py-2 text-sm font-medium transition
                {{ $isActive ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
