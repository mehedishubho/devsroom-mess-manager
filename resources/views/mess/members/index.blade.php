@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Members') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __(":count active members", ['count' => $activeCount]) }}</p>
        </div>
        <a href="{{ route('mess.members.create') }}" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            {{ __('Add member') }}
        </a>
    </header>

    <div class="mb-4">
        <label for="member-search" class="sr-only">{{ __('Search members') }}</label>
        <div class="relative">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input
                type="search"
                id="member-search"
                data-member-search
                value="{{ $search ?? '' }}"
                placeholder="{{ __('Search by name, mobile, email, or room…') }}"
                class="input pl-10"
                autocomplete="off"
            />
        </div>
    </div>

    <div data-member-list>
        @include('mess.members._list', ['members' => $members, 'activeCount' => $activeCount, 'search' => $search ?? ''])
    </div>

    @once
        <script>
            (function () {
                const input = document.querySelector('[data-member-search]');
                const list = document.querySelector('[data-member-list]');
                if (!input || !list) return;

                let timer;
                let lastQ = '';
                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(async function () {
                        const q = input.value.trim();
                        if (q === lastQ) return;
                        lastQ = q;
                        const res = await fetch('{{ route('mess.members.search') }}?q=' + encodeURIComponent(q), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (res.ok) {
                            list.innerHTML = await res.text();
                        }
                    }, 300);
                });
            })();
        </script>
    @endonce
@endsection
