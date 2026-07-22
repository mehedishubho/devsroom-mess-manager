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

    <style>
        /* Chevron rotates when the Cloud credentials accordion is open. */
        details.backup-creds > summary .chev { transition: transform .15s ease; }
        details.backup-creds[open] > summary .chev { transform: rotate(90deg); }
    </style>

    {{-- Cloud credentials — collapsible (collapsed by default to keep the form tidy).
         Auto-opens when there are validation errors so a failed submit doesn't
         hide error fields inside the collapsed section. --}}
    <details class="backup-creds rounded-lg border border-slate-200 bg-slate-50/60" @if ($errors->any()) open @endif>
        <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
            <svg class="chev h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            <span>{{ __('Cloud credentials') }}</span>
            @if (($gdriveConfigured ?? false) || ($r2Configured ?? false))
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-normal text-emerald-700">{{ __('configured') }}</span>
            @else
                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-normal text-slate-600">{{ __('not configured') }}</span>
            @endif
        </summary>

        <div class="border-t border-slate-200 bg-white p-4">
            <p class="mb-3 text-xs text-slate-500">{{ __('Stored encrypted in the database. Leave secret fields blank to keep the saved value. Save before testing. .env values still work as a fallback.') }}</p>
            <fieldset class="grid grid-cols-1 gap-4 sm:grid-cols-2">

        {{-- Google Drive credentials --}}
        <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <div class="mb-2 flex items-center justify-between">
                <p class="font-medium text-slate-900">{{ __('Google Drive') }}</p>
                <button type="button" data-test-provider="gdrive" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">{{ __('Test connection') }}</button>
            </div>
            <div class="space-y-2">
                <label class="block">
                    <span class="text-xs text-slate-600">{{ __('Client ID') }}</span>
                    <input type="text" name="gdrive_client_id" value="{{ old('gdrive_client_id', $config->gdrive_client_id) }}" class="input mt-0.5" autocomplete="off" />
                </label>
                <label class="block">
                    <span class="text-xs text-slate-600">{{ __('Client secret') }} @if ($gdriveSecretSaved ?? false)<span class="text-emerald-700">(saved ✓)</span>@endif</span>
                    <input type="password" name="gdrive_client_secret" class="input mt-0.5" placeholder="{{ ($gdriveSecretSaved ?? false) ? '•••••• (leave blank to keep saved)' : '' }}" autocomplete="new-password" />
                </label>
                <label class="block">
                    <span class="text-xs text-slate-600">{{ __('Refresh token') }} @if ($gdriveRefreshSaved ?? false)<span class="text-emerald-700">(saved ✓)</span>@endif</span>
                    <input type="password" name="gdrive_refresh_token" class="input mt-0.5" placeholder="{{ ($gdriveRefreshSaved ?? false) ? '•••••• (leave blank to keep saved)' : '' }}" autocomplete="new-password" />
                </label>
                <label class="block">
                    <span class="text-xs text-slate-600">{{ __('Folder ID') }}</span>
                    <input type="text" name="gdrive_folder_id" value="{{ old('gdrive_folder_id', $config->gdrive_folder_id) }}" class="input mt-0.5" autocomplete="off" />
                </label>
            </div>
        </div>

        {{-- Cloudflare R2 credentials --}}
        <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <div class="mb-2 flex items-center justify-between">
                <p class="font-medium text-slate-900">{{ __('Cloudflare R2') }}</p>
                <button type="button" data-test-provider="r2" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">{{ __('Test connection') }}</button>
            </div>
            <div class="space-y-2">
                <label class="block">
                    <span class="text-xs text-slate-600">{{ __('Access key ID') }}</span>
                    <input type="text" name="r2_key" value="{{ old('r2_key', $config->r2_key) }}" class="input mt-0.5" autocomplete="off" />
                </label>
                <label class="block">
                    <span class="text-xs text-slate-600">{{ __('Secret access key') }} @if ($r2SecretSaved ?? false)<span class="text-emerald-700">(saved ✓)</span>@endif</span>
                    <input type="password" name="r2_secret" class="input mt-0.5" placeholder="{{ ($r2SecretSaved ?? false) ? '•••••• (leave blank to keep saved)' : '' }}" autocomplete="new-password" />
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="text-xs text-slate-600">{{ __('Region') }}</span>
                        <input type="text" name="r2_region" value="{{ old('r2_region', $config->r2_region ?? 'auto') }}" class="input mt-0.5" autocomplete="off" />
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-600">{{ __('Bucket') }}</span>
                        <input type="text" name="r2_bucket" value="{{ old('r2_bucket', $config->r2_bucket) }}" class="input mt-0.5" autocomplete="off" />
                    </label>
                </div>
                <label class="block">
                    <span class="text-xs text-slate-600">{{ __('S3 endpoint') }}</span>
                    <input type="text" name="r2_endpoint" value="{{ old('r2_endpoint', $config->r2_endpoint) }}" class="input mt-0.5" placeholder="https://<account>.r2.cloudflarestorage.com" autocomplete="off" />
                </label>
                <label class="flex items-center gap-2 text-slate-700">
                    <input type="hidden" name="r2_use_path_style" value="0" />
                    <input type="checkbox" name="r2_use_path_style" value="1" @checked(old('r2_use_path_style', (bool) $config->r2_use_path_style)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    {{ __('Use path-style endpoint') }}
                </label>
            </div>
        </div>
            </fieldset>
        </div>
    </details>

    {{-- Test-connection result target (filled by the fetch handler below) --}}
    <p id="backup-test-result" class="hidden text-xs"></p>

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

    <script>
        // Test connection — POSTs to the test route (JSON) against the SAVED
        // credentials. Reuses the page's CSRF token. No build step / dep.
        document.querySelectorAll('[data-test-provider]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var provider = btn.getAttribute('data-test-provider');
                var result = document.getElementById('backup-test-result');
                btn.disabled = true;
                var original = btn.textContent;
                btn.textContent = '{{ __('Testing…') }}';
                result.className = 'text-xs text-slate-600';
                result.textContent = '{{ __('Testing…') }}';
                result.classList.remove('hidden');
                try {
                    var resp = await fetch('{{ url('/dashboard/backups/test') }}/' + encodeURIComponent(provider), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    var data = await resp.json();
                    result.className = 'text-xs ' + (data.ok ? 'text-emerald-700' : 'text-rose-700');
                    result.textContent = data.message;
                } catch (e) {
                    result.className = 'text-xs text-rose-700';
                    result.textContent = e.message;
                } finally {
                    btn.disabled = false;
                    btn.textContent = original;
                }
            });
        });
    </script>
</form>
