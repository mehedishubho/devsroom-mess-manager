@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Daily meal grid') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Mark which meals each member took on this date. Save once at the bottom.') }}</p>
        </div>
        <x-mess-date-nav :date="$date" />
    </header>

    <form method="POST" action="{{ route('mess.meals.save') }}" data-meal-grid-form>
        @csrf
        <input type="hidden" name="date" value="{{ $date }}" />

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <button type="button" data-preset="all" class="inline-flex min-h-[44px] items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                {{ __('Mark all 3 meals') }}
            </button>
            <button type="button" data-preset="none" class="inline-flex min-h-[44px] items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                {{ __('Mark all 0 meals') }}
            </button>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Member') }}</th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Breakfast') }}</th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Lunch') }}</th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Dinner') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($rows as $row)
                        <tr @class(['opacity-60' => !$row->editable]) aria-disabled="{{ $row->editable ? 'false' : 'true' }}">
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium text-slate-900">{{ $row->member->name }}</div>
                                @if ($row->member->room_or_seat)
                                    <div class="text-xs text-slate-500">{{ $row->member->room_or_seat }}</div>
                                @endif
                                @if (!$row->editable)
                                    <p class="mt-1 text-xs text-amber-700">{{ __('On meal off until :date', ['date' => $row->meal_off_until->format('d M')]) }}</p>
                                @endif
                            </td>
                            @foreach (['breakfast', 'lunch', 'dinner'] as $meal)
                                <td class="px-4 py-3 text-center">
                                    <input type="hidden" name="entries[{{ $row->member->id }}][member_id]" value="{{ $row->member->id }}" />
                                    <input type="checkbox"
                                        name="entries[{{ $row->member->id }}][{{ $meal }}]"
                                        value="1"
                                        @checked($row->{$meal})
                                        @disabled(!$row->editable)
                                        data-meal-checkbox
                                        class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                                    />
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-600">
                                {{ __('No active members yet. Add members to start recording meals.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                {{ __('Save all changes') }}
            </button>
        </div>
    </form>

    @once
        <script>
            (function () {
                document.querySelectorAll('[data-preset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const preset = btn.getAttribute('data-preset');
                        const value = preset === 'all';
                        document.querySelectorAll('[data-meal-checkbox]').forEach(function (cb) {
                            if (!cb.disabled) cb.checked = value;
                        });
                    });
                });
            })();
        </script>
    @endonce
@endsection
