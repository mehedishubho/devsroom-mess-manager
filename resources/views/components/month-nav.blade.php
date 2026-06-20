@props([
    'year',
    'month',
    'route' => 'mess.reports.monthly',
    'extra' => [], // additional query params to carry over (e.g. member_id)
])

@php
    $carbon = \Carbon\Carbon::create((int) $year, (int) $month, 1);
    $prev = ['year' => $carbon->copy()->subMonth()->year, 'month' => $carbon->copy()->subMonth()->month];
    $next = ['year' => $carbon->copy()->addMonth()->year, 'month' => $carbon->copy()->addMonth()->month];
    $thisMonth = ['year' => now()->year, 'month' => now()->month];
    $buildQuery = function (array $ym) use ($extra) {
        return array_merge($ym, $extra);
    };
@endphp

<div class="flex flex-wrap items-center gap-2" data-month-nav>
    <a href="{{ route($route, $buildQuery($prev)) }}"
       class="btn btn-secondary aspect-square p-2"
       aria-label="{{ __('Previous month') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
    </a>

    <select data-month-select
            class="input w-auto">
        @for ($back = 23; $back >= 0; $back--)
            @php
                $cursor = now()->copy()->subMonths($back);
                $y = $cursor->year;
                $m = $cursor->month;
            @endphp
            <option value="{{ route($route, $buildQuery(['year' => $y, 'month' => $m])) }}"
                @selected(((int) $y === (int) $year && (int) $m === (int) $month))>
                {{ $cursor->translatedFormat('F Y') }}
            </option>
        @endfor
    </select>

    <a href="{{ route($route, $buildQuery($next)) }}"
       class="btn btn-secondary aspect-square p-2"
       aria-label="{{ __('Next month') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
    </a>

    <a href="{{ route($route, $buildQuery($thisMonth)) }}"
       class="btn btn-secondary">
        {{ __('This month') }}
    </a>
</div>

@once
    <script>
        (function () {
            document.querySelectorAll('[data-month-nav]').forEach(function (nav) {
                const select = nav.querySelector('[data-month-select]');
                if (!select) return;
                select.addEventListener('change', function () {
                    if (select.value) {
                        window.location.href = select.value;
                    }
                });
            });
        })();
    </script>
@endonce
