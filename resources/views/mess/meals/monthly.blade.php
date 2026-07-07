@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Monthly meal grid') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Mark meals for each member across the full month. Save once.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('mess.meals.monthly', ['month' => \Carbon\Carbon::parse($monthStr)->subMonth()->format('Y-m')]) }}"
               class="btn btn-secondary btn-sm" aria-label="{{ __('Previous month') }}">&larr;</a>
            <input type="month" value="{{ $monthStr }}" data-month-picker
                   class="input w-auto text-sm">
            <a href="{{ route('mess.meals.monthly', ['month' => \Carbon\Carbon::parse($monthStr)->addMonth()->format('Y-m')]) }}"
               class="btn btn-secondary btn-sm" aria-label="{{ __('Next month') }}">&rarr;</a>
        </div>
    </header>

    <form method="POST" action="{{ route('mess.meals.monthly.save') }}" data-monthly-meal-form>
        @csrf
        <input type="hidden" name="month" value="{{ $monthStr }}" />

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <button type="button" data-preset="all" class="btn btn-secondary btn-sm">
                {{ __('Mark all 3 meals (all members, all days)') }}
            </button>
            <button type="button" data-preset="none" class="btn btn-secondary btn-sm">
                {{ __('Clear all meals') }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200" data-monthly-grid>
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 bg-slate-50 px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-4" style="min-width:140px">
                            {{ __('Member') }}
                        </th>
                        @for ($d = 1; $d <= $days_in_month; $d++)
                            @php
                                $date = \Carbon\Carbon::parse($monthStr)->day($d);
                                $isWeekend = $date->isWeekend();
                                $dateStr = $date->format('Y-m-d');
                                $isClosed = in_array($dateStr, $closed_dates);
                            @endphp
                            <th scope="col" class="px-1 py-2 text-center text-xs font-medium tracking-wider sm:px-2 {{ $isWeekend ? 'text-amber-600' : 'text-slate-500' }} {{ $isClosed ? 'bg-red-50' : '' }}"
                                style="min-width:{{ $isClosed ? '34' : '60' }}px">
                                <div>{{ $d }}</div>
                                <div class="text-[10px] uppercase">{{ $date->format('D') }}</div>
                                @if ($isClosed)
                                    <div class="text-[9px] text-red-500">&#x2716;</div>
                                @endif
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($members as $row)
                        <tr>
                            <td class="sticky left-0 z-10 bg-white px-3 py-2 text-sm sm:px-4" style="min-width:140px">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900 truncate max-w-[100px]">{{ $row->member->name }}</span>
                                    <div class="flex gap-0.5" role="group" aria-label="{{ __('Row actions for :name', ['name' => $row->member->name]) }}">
                                        <button type="button" data-row-preset="all" data-row-member="{{ $row->member->id }}"
                                                class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:bg-emerald-100 hover:text-emerald-700"
                                                title="{{ __('All meals') }}">&#x2713;</button>
                                        <button type="button" data-row-preset="none" data-row-member="{{ $row->member->id }}"
                                                class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 hover:bg-red-100 hover:text-red-700"
                                                title="{{ __('Clear all') }}">&#x2717;</button>
                                    </div>
                                </div>
                                @if ($row->member->room_or_seat)
                                    <div class="text-xs text-slate-500 truncate">{{ $row->member->room_or_seat }}</div>
                                @endif
                            </td>
                            @foreach ($row->days as $day)
                                <td class="px-0.5 py-1 text-center sm:px-1 sm:py-2 {{ $day->editable ? '' : 'bg-slate-50' }}"
                                    @if (! $day->editable)
                                    title="{{ $day->reason ?? __('Not editable') }}"
                                    @endif
                                >
                                    @if ($day->editable)
                                        <input type="hidden" name="entries[{{ $row->member->id }}_{{ $day->day }}][member_id]" value="{{ $row->member->id }}" />
                                        <input type="hidden" name="entries[{{ $row->member->id }}_{{ $day->day }}][date]" value="{{ $day->date }}" />
                                        <div class="flex flex-col items-center gap-0.5">
                                            <label class="flex cursor-pointer items-center gap-0.5">
                                                <input type="checkbox"
                                                    name="entries[{{ $row->member->id }}_{{ $day->day }}][breakfast]"
                                                    value="1"
                                                    @checked($day->breakfast)
                                                    data-meal-checkbox
                                                    data-member="{{ $row->member->id }}"
                                                    data-date="{{ $day->date }}"
                                                    data-meal="breakfast"
                                                    class="h-3 w-3 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 sm:h-3.5 sm:w-3.5"
                                                    aria-label="{{ __(':date :meal for :name', ['date' => $day->date, 'meal' => __('Breakfast'), 'name' => $row->member->name]) }}">
                                                <span class="text-[10px] text-slate-500 sm:text-xs">B</span>
                                            </label>
                                            <label class="flex cursor-pointer items-center gap-0.5">
                                                <input type="checkbox"
                                                    name="entries[{{ $row->member->id }}_{{ $day->day }}][lunch]"
                                                    value="1"
                                                    @checked($day->lunch)
                                                    data-meal-checkbox
                                                    data-member="{{ $row->member->id }}"
                                                    data-date="{{ $day->date }}"
                                                    data-meal="lunch"
                                                    class="h-3 w-3 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 sm:h-3.5 sm:w-3.5"
                                                    aria-label="{{ __(':date :meal for :name', ['date' => $day->date, 'meal' => __('Lunch'), 'name' => $row->member->name]) }}">
                                                <span class="text-[10px] text-slate-500 sm:text-xs">L</span>
                                            </label>
                                            <label class="flex cursor-pointer items-center gap-0.5">
                                                <input type="checkbox"
                                                    name="entries[{{ $row->member->id }}_{{ $day->day }}][dinner]"
                                                    value="1"
                                                    @checked($day->dinner)
                                                    data-meal-checkbox
                                                    data-member="{{ $row->member->id }}"
                                                    data-date="{{ $day->date }}"
                                                    data-meal="dinner"
                                                    class="h-3 w-3 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 sm:h-3.5 sm:w-3.5"
                                                    aria-label="{{ __(':date :meal for :name', ['date' => $day->date, 'meal' => __('Dinner'), 'name' => $row->member->name]) }}">
                                                <span class="text-[10px] text-slate-500 sm:text-xs">D</span>
                                            </label>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400" title="{{ $day->reason ?? '' }}">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $days_in_month + 1 }}" class="px-4 py-6 text-center text-sm text-slate-600">
                                {{ __('No active members yet. Add members to start recording meals.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary">
                {{ __('Save all changes') }}
            </button>
            <a href="{{ route('mess.meals.index') }}" class="btn btn-ghost btn-sm">
                {{ __('Switch to daily grid') }}
            </a>
        </div>
    </form>

    @once
        <script>
            (function () {
                // Month picker navigation
                const monthPicker = document.querySelector('[data-month-picker]');
                if (monthPicker) {
                    monthPicker.addEventListener('change', function () {
                        if (monthPicker.value) {
                            window.location.href = '{{ route('mess.meals.monthly') }}' + '?month=' + monthPicker.value;
                        }
                    });
                }

                // Global presets (all/none) affect every editable checkbox.
                document.querySelectorAll('[data-preset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const preset = btn.getAttribute('data-preset');
                        const value = preset === 'all';
                        document.querySelectorAll('[data-meal-checkbox]').forEach(function (cb) {
                            if (!cb.disabled) cb.checked = value;
                        });
                    });
                });

                // Per-member presets
                document.querySelectorAll('[data-row-preset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const preset = btn.getAttribute('data-row-preset');
                        const memberId = btn.getAttribute('data-row-member');
                        const value = preset === 'all';
                        document.querySelectorAll(
                            '[data-meal-checkbox][data-member="' + memberId + '"]'
                        ).forEach(function (cb) {
                            if (!cb.disabled) cb.checked = value;
                        });
                    });
                });
            })();
        </script>
    @endonce
@endsection
