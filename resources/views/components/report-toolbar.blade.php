@props([
    'route',
    'year' => null,
    'month' => null,
    'showExports' => false,
    'extra' => [],
    'filters' => [],  // additional filter params (from/to / category_id / method) carried to export
])

<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex-1 min-w-[240px]">
        @if ($year !== null && $month !== null)
            <x-month-nav :year="$year" :month="$month" :route="$route" :extra="$extra" />
        @endif
    </div>

    @if ($showExports)
        @php
            // Build the query params for the .pdf + .xlsx routes (named "{route}.pdf" / "{route}.xlsx").
            // Filter out empty values so the URL stays clean. Year/month + extra + filters
            // all merge so the export matches what's on screen (D-18 sticky).
            $exportParams = array_filter(array_merge(
                ['year' => $year, 'month' => $month],
                $extra,
                $filters
            ), fn ($v) => $v !== null && $v !== '' && $v !== []);
            $pdfRoute = $route . '.pdf';
            $xlsxRoute = $route . '.xlsx';
            $pdfHref = Route::has($pdfRoute) ? route($pdfRoute, $exportParams) : null;
            $xlsxHref = Route::has($xlsxRoute) ? route($xlsxRoute, $exportParams) : null;
        @endphp
        <div class="flex items-center gap-2">
            @if ($pdfHref)
                <a href="{{ $pdfHref }}"
                   target="_blank"
                   class="inline-flex min-h-[44px] items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100"
                   title="{{ __('Download PDF') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                    </svg>
                    {{ __('PDF') }}
                </a>
            @else
                <button type="button" disabled aria-disabled="true"
                        class="inline-flex min-h-[44px] cursor-not-allowed items-center gap-1 rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-medium text-slate-400">
                    {{ __('PDF') }}
                </button>
            @endif
            @if ($xlsxHref)
                <a href="{{ $xlsxHref }}"
                   class="inline-flex min-h-[44px] items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100"
                   title="{{ __('Download Excel') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v18M21 16.5 16.5 21m0 0L12 16.5m4.5 4.5V3"/>
                    </svg>
                    {{ __('Excel') }}
                </a>
            @else
                <button type="button" disabled aria-disabled="true"
                        class="inline-flex min-h-[44px] cursor-not-allowed items-center gap-1 rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-medium text-slate-400">
                    {{ __('Excel') }}
                </button>
            @endif
        </div>
    @endif
</div>
