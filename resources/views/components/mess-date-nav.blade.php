@props(['date', 'route' => 'mess.meals.index'])

@php
    $carbon = \Carbon\Carbon::parse($date);
    $today = \Carbon\Carbon::now(config('app.timezone'))->toDateString();
    $prev = $carbon->copy()->subDay()->toDateString();
    $next = $carbon->copy()->addDay()->toDateString();
@endphp

<div class="flex items-center gap-2" data-date-nav>
    <a href="{{ route($route, ['date' => $prev]) }}" class="btn btn-secondary aspect-square p-2" aria-label="{{ __('Previous day') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
    </a>

    <input type="date" name="date" value="{{ $date }}" data-date-input
        class="input input-date w-auto" />

    <a href="{{ route($route, ['date' => $next]) }}" class="btn btn-secondary aspect-square p-2" aria-label="{{ __('Next day') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
    </a>

    <a href="{{ route($route, ['date' => $today]) }}" class="btn btn-secondary">
        {{ __('Today') }}
    </a>
</div>

@once
    <script>
        (function () {
            const nav = document.querySelector('[data-date-nav]');
            const input = nav?.querySelector('[data-date-input]');
            if (!input) return;
            input.addEventListener('change', function () {
                if (input.value) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('date', input.value);
                    window.location.href = url.toString();
                }
            });
        })();
    </script>
@endonce
