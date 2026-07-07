@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Disabled Days — :name', ['name' => $member->name]) }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Days when this member cannot take meals.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('mess.members.disabled-days.index', ['member' => $member, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}"
               class="btn btn-secondary btn-sm">&larr;</a>
            <input type="month" value="{{ $month->format('Y-m') }}" data-month-picker
                   class="input w-auto text-sm">
            <a href="{{ route('mess.members.disabled-days.index', ['member' => $member, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}"
               class="btn btn-secondary btn-sm">&rarr;</a>
        </div>
    </header>

    <div class="mb-4">
        <a href="{{ route('mess.members.show', $member) }}" class="text-sm text-emerald-700 hover:underline">
            &larr; {{ __('Back to :name\'s profile', ['name' => $member->name]) }}
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $month->translatedFormat('F Y') }}</h2>
                </div>
                @if ($disabledDays->isEmpty())
                    <div class="px-4 py-6 text-center text-sm text-slate-500">
                        {{ __('No disabled days for this member this month.') }}
                    </div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($disabledDays as $disabledDay)
                            <li class="flex items-center justify-between px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-sm font-semibold text-amber-700">
                                        {{ $disabledDay->date->format('d') }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">
                                            {{ $disabledDay->date->format('l, d M Y') }}
                                        </p>
                                        @if ($disabledDay->reason)
                                            <p class="text-xs text-slate-500">{{ $disabledDay->reason }}</p>
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('mess.members.disabled-days.destroy', [$member, $disabledDay]) }}"
                                      onsubmit="return confirm('{{ __('Re-enable this day?') }}');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('Re-enable') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Disable a day') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('This member cannot take meals on disabled days.') }}</p>

                <form method="POST" action="{{ route('mess.members.disabled-days.store', $member) }}" class="mt-4 flex flex-col gap-3">
                    @csrf

                    <div class="flex flex-col gap-1">
                        <label for="date" class="text-sm font-medium text-slate-900">{{ __('Date') }}</label>
                        <input type="date" name="date" id="date" required
                               class="input @error('date') border-red-500 @enderror"
                               value="{{ old('date', $month->copy()->format('Y-m-d')) }}">
                        @error('date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="reason" class="text-sm font-medium text-slate-900">{{ __('Reason') }}</label>
                        <input type="text" name="reason" id="reason" maxlength="255"
                               class="input @error('reason') border-red-500 @enderror"
                               placeholder="{{ __('e.g. Vacation, absence...') }}">
                        @error('reason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">{{ __('Disable day') }}</button>
                </form>
            </div>
        </div>
    </div>

    @once
        <script>
            (function () {
                const picker = document.querySelector('[data-month-picker]');
                if (picker) {
                    picker.addEventListener('change', function () {
                        if (picker.value) {
                            const url = new URL(window.location.href);
                            url.searchParams.set('month', picker.value);
                            window.location.href = url.toString();
                        }
                    });
                }
            })();
        </script>
    @endonce
@endsection
