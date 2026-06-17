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
       class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
       aria-label="{{ __('Previous month') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
    </a>

    <select data-month-select
            class="min-h-[44px] rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
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
       class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
       aria-label="{{ __('Next month') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
    </a>

    <a href="{{ route($route, $buildQuery($thisMonth)) }}"
       class="inline-flex min-h-[44px] items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
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
