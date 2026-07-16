@props([
    'year',
    'month',
    'route' => 'mess.reports.monthly',
    'extra' => [], // additional query params to carry over (e.g. member_id)
    // from / to: data-driven range (associative array with year + month keys).
    // null falls back to the last 12 months ending at the current month.
    'from' => null,
    'to' => null,
])

@php
    /**
     * Task 4 (quick-260717-2q3) — data-driven month picker.
     *
     * Replaces the hardcoded 24-month window (back=23..0) with a passed-in
     * from/to range computed by ReportService::availableMonthRange(). When
     * $from/$to are NOT supplied (e.g. a view that hasn't been wired yet),
     * the component falls back to the last 12 months ending at the current
     * month — a tighter default than the old 24-month window so the picker
     * never shows stale "dummy" months.
     */
    $carbon = \Carbon\Carbon::create((int) $year, (int) $month, 1);
    $prev = ['year' => $carbon->copy()->subMonth()->year, 'month' => $carbon->copy()->subMonth()->month];
    $next = ['year' => $carbon->copy()->addMonth()->year, 'month' => $carbon->copy()->addMonth()->month];
    $thisMonth = ['year' => now()->year, 'month' => now()->month];
    $buildQuery = function (array $ym) use ($extra) {
        return array_merge($ym, $extra);
    };

    // Compute the iteration window from $from/$to (or default 12-month window).
    $start = $from
        ? \Carbon\Carbon::create((int) $from['year'], (int) $from['month'], 1)->startOfMonth()
        : now()->copy()->subMonths(11)->startOfMonth();
    $end = $to
        ? \Carbon\Carbon::create((int) $to['year'], (int) $to['month'], 1)->startOfMonth()
        : now()->copy()->startOfMonth();
    // Defensive clamps — never show months past current, never start after end.
    if ($end->greaterThan(now()->startOfMonth())) {
        $end = now()->startOfMonth();
    }
    if ($start->greaterThan($end)) {
        $start = $end->copy()->subMonths(11);
    }
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
        @php
            $cursor = $start->copy();
        @endphp
        @while ($cursor->lessThanOrEqualTo($end))
            @php
                $y = $cursor->year;
                $m = $cursor->month;
            @endphp
            <option value="{{ route($route, $buildQuery(['year' => $y, 'month' => $m])) }}"
                @selected(((int) $y === (int) $year && (int) $m === (int) $month))>
                {{ $cursor->translatedFormat('F Y') }}
            </option>
            @php
                $cursor->addMonth();
            @endphp
        @endwhile
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
