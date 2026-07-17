{{--
    Inline Configure form for the Backups page (Task: move configure onto
    Dashboard > Backups). Posts to dashboard.backups.configure.update.
    Reuses $config / $spacesConfigured / $gdriveConfigured / $r2Configured
    passed by BackupController::indexData().
--}}
<form method="POST" action="{{ route('dashboard.backups.configure.update') }}" class="space-y-5">
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

    {{-- Storage providers (DB-toggled; T-2q3-01 super-admin-only write) --}}
    <fieldset class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <legend class="mb-2 text-sm font-semibold text-slate-900">{{ __('Storage providers') }}</legend>

        {{-- Google Drive column --}}
        <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <p class="font-medium text-slate-900">{{ __('Google Drive') }}</p>
            @if ($gdriveConfigured)
                <p class="mt-0.5 text-xs text-emerald-700">{{ __('Credentials configured') }} ✓</p>
            @else
                <p class="mt-0.5 text-xs text-rose-700">{{ __('Not configured — set GOOGLE_DRIVE_* env vars to enable.') }}</p>
            @endif
            <div class="mt-3 space-y-2">
                <label class="flex items-center gap-2 text-slate-700">
                    <input type="hidden" name="gdrive_backup" value="0" />
                    <input type="checkbox" name="gdrive_backup" value="1" @checked(old('gdrive_backup', (bool) $config->gdrive_backup)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    {{ __('Use for backups') }}
                </label>
                <label class="flex items-center gap-2 text-slate-700">
                    <input type="hidden" name="gdrive_uploads" value="0" />
                    <input type="checkbox" name="gdrive_uploads" value="1" @checked(old('gdrive_uploads', (bool) $config->gdrive_uploads)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    {{ __('Use for uploads mirror') }}
                </label>
            </div>
        </div>

        {{-- Cloudflare R2 column --}}
        <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <p class="font-medium text-slate-900">{{ __('Cloudflare R2') }}</p>
            @if ($r2Configured)
                <p class="mt-0.5 text-xs text-emerald-700">{{ __('Credentials configured') }} ✓</p>
            @else
                <p class="mt-0.5 text-xs text-rose-700">{{ __('Not configured — set R2_* env vars to enable.') }}</p>
            @endif
            <div class="mt-3 space-y-2">
                <label class="flex items-center gap-2 text-slate-700">
                    <input type="hidden" name="r2_backup" value="0" />
                    <input type="checkbox" name="r2_backup" value="1" @checked(old('r2_backup', (bool) $config->r2_backup)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    {{ __('Use for backups') }}
                </label>
                <label class="flex items-center gap-2 text-slate-700">
                    <input type="hidden" name="r2_uploads" value="0" />
                    <input type="checkbox" name="r2_uploads" value="1" @checked(old('r2_uploads', (bool) $config->r2_uploads)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    {{ __('Use for uploads mirror') }}
                </label>
            </div>
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

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">{{ __('Save configuration') }}</button>
    </div>
</form>
