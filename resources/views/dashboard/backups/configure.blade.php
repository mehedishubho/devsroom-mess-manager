@extends('layouts.app')

@section('content')
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Configure backups') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Set the backup schedule and retention. Changes take effect immediately.') }}</p>
        </div>
        <a href="{{ route('dashboard.backups.index') }}" class="btn btn-secondary">{{ __('Back') }}</a>
    </header>

    <form method="POST" action="{{ route('dashboard.backups.configure.update') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        @csrf
        @method('PUT')

        {{-- Destinations (read-only display) --}}
        <fieldset class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm font-semibold text-slate-900">{{ __('Destinations') }}</legend>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                <p class="font-medium text-emerald-700">{{ __('Local — default') }} ✓</p>
                <p class="mt-0.5 text-xs text-slate-500">storage/app/backups</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                @if ($spacesConfigured)
                    <p class="font-medium text-emerald-700">{{ __('Spaces — configured') }} ✓</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ config('filesystems.disks.backups.bucket') }} · {{ config('filesystems.disks.backups.region') }}</p>
                @else
                    <p class="font-medium text-slate-600">{{ __('Spaces — not configured') }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Add DO_SPACES_* credentials in .env to enable off-server mirroring.') }}</p>
                @endif
            </div>
        </fieldset>

        {{-- Schedule --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <label for="frequency" class="text-sm font-medium text-slate-900">{{ __('Frequency') }}</label>
                <select name="frequency" id="frequency" class="input">
                    <option value="off" @selected(old('frequency', $config->frequency) === 'off')>{{ __('Off (no automatic backups)') }}</option>
                    <option value="daily" @selected(old('frequency', $config->frequency) === 'daily')>{{ __('Daily') }}</option>
                    <option value="weekly" @selected(old('frequency', $config->frequency) === 'weekly')>{{ __('Weekly') }}</option>
                    <option value="monthly" @selected(old('frequency', $config->frequency) === 'monthly')>{{ __('Monthly') }}</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label for="run_at" class="text-sm font-medium text-slate-900">{{ __('Run at') }}</label>
                <input type="time" name="run_at" id="run_at" value="{{ old('run_at', $config->runAtLabel()) }}" class="input" />
                <p class="text-xs text-slate-500">{{ __('24-hour time, e.g. 01:30.') }}</p>
                @error('run_at') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Retention / rotation --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <label for="keep_all_days" class="text-sm font-medium text-slate-900">{{ __('Keep all backups (days)') }}</label>
                <input type="number" name="keep_all_days" id="keep_all_days" min="1" max="3650" value="{{ old('keep_all_days', $config->keep_all_days) }}" class="input" />
                <p class="text-xs text-slate-500">{{ __('Every backup is kept for at least this many days.') }}</p>
                @error('keep_all_days') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-1">
                <label for="max_mb" class="text-sm font-medium text-slate-900">{{ __('Storage cap (MB)') }}</label>
                <input type="number" name="max_mb" id="max_mb" min="100" max="1000000" value="{{ old('max_mb', $config->max_mb) }}" class="input" />
                <p class="text-xs text-slate-500">{{ __('Oldest backups are deleted once this cap is exceeded.') }}</p>
                @error('max_mb') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Save configuration') }}</button>
            <a href="{{ route('dashboard.backups.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
