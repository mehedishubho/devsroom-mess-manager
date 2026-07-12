@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('My notification preferences') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Pick how you want to be notified. You can only choose channels the mess admin has enabled. The in-app bell is always on.') }}</p>
    </header>

    @if ($available->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {{ __('Your mess admin hasn’t enabled any external channels yet. You’ll still receive in-app notifications via the bell icon at the top of the screen.') }}
        </div>
    @else
        <form method="POST" action="{{ route('notification-preferences.update') }}">
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
                @if (! $hasPreference)
                    <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                        {{ __('You’re currently receiving every channel the admin has enabled. Tick the ones you want below and save to narrow it down.') }}
                    </div>
                @endif

                <fieldset class="flex flex-col gap-3">
                    @foreach ($available as $key => $block)
                        @php $checked = $hasPreference ? in_array($key, $selected, true) : true; @endphp
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                            <input type="checkbox" name="channels[]" value="{{ $key }}" {{ $checked ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                            <span class="font-medium text-slate-900">{{ $channelLabels[$key] ?? $key }}</span>
                        </label>
                    @endforeach
                </fieldset>
            </div>

            <div class="mt-6">
                <button type="submit" class="btn btn-primary">{{ __('Save preferences') }}</button>
            </div>
        </form>
    @endif
@endsection
