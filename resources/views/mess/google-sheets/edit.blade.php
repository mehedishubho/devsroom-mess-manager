@extends('layouts.app')
@section('content')
    @php
        $authMode = old('auth_mode', $config?->auth_mode ?? \App\Models\GoogleSheetsConfig::AUTH_SERVICE_ACCOUNT);
        $isServiceAccount = $authMode === \App\Models\GoogleSheetsConfig::AUTH_SERVICE_ACCOUNT;
    @endphp

    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Google Sheets') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Mirror the mess data to a Google Spreadsheet. The database stays the source of truth — a Sheets outage never blocks a save.') }}</p>
    </header>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Status --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Status') }}</h2>
        <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-slate-500">{{ __('Last synced') }}</dt>
                <dd class="font-medium text-slate-900">{{ $config?->last_synced_at?->diffForHumans() ?? __('Never') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('Queued rows') }}</dt>
                <dd class="font-medium text-slate-900">{{ $pendingCount }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">{{ __('Spreadsheet') }}</dt>
                <dd class="font-medium text-slate-900">
                    @if ($sheetUrl)
                        <a href="{{ $sheetUrl }}" target="_blank" rel="noopener" class="text-emerald-700 underline">{{ __('Open') }}</a>
                    @else
                        {{ __('Not set') }}
                    @endif
                </dd>
            </div>
        </dl>

        @if ($config?->last_error)
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                <span class="font-semibold">{{ __('Last sync error') }}</span>
                <span class="text-amber-700">{{ $config->last_error_at?->diffForHumans() }}</span>
                <div class="mt-1 break-words">{{ $config->last_error }}</div>
            </div>
        @endif
    </section>

    <form method="POST" action="{{ route('mess.google-sheets.update') }}">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Connection') }}</h2>

            <label class="mt-4 flex items-center gap-2">
                <input type="checkbox" name="enabled" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" {{ old('enabled', $config?->enabled) ? 'checked' : '' }} />
                <span class="font-medium text-slate-900">{{ __('Enable Google Sheets sync') }}</span>
            </label>

            {{-- Auth mode --}}
            <div class="mt-4">
                <span class="text-sm font-medium text-slate-700">{{ __('Authentication') }}</span>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:gap-6">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="auth_mode" value="service_account" class="h-4 w-4 border-slate-300 text-emerald-600" {{ $isServiceAccount ? 'checked' : '' }} />
                        <span class="text-sm text-slate-800">{{ __('Service account') }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="auth_mode" value="oauth" class="h-4 w-4 border-slate-300 text-emerald-600" {{ ! $isServiceAccount ? 'checked' : '' }} />
                        <span class="text-sm text-slate-800">{{ __('OAuth 2.0 (refresh token)') }}</span>
                    </label>
                </div>
            </div>

            {{-- Service account --}}
            <div id="gsheets-service-account" class="mt-4 rounded-lg border border-slate-200 p-4">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-slate-700">
                        {{ __('Service-account key (JSON)') }}
                        @if ($hasServiceAccountKey)
                            <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700">{{ __('saved ✓') }}</span>
                        @endif
                    </label>
                    <textarea name="service_account_json" rows="6" class="input font-mono text-xs" autocomplete="off" placeholder="{{ $hasServiceAccountKey ? __('•••••••• (leave blank to keep the saved key)') : __('Paste the service-account JSON key here') }}">{{ old('service_account_json') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Create a service account in Google Cloud, enable the Google Sheets API, and download a JSON key.') }}</p>
                </div>

                @if ($serviceAccountEmail)
                    <div class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-700">
                        {{ __('Share the target spreadsheet with this address (Editor):') }}
                        <code class="ml-1 font-mono text-slate-900">{{ $serviceAccountEmail }}</code>
                    </div>
                @endif
            </div>

            {{-- OAuth --}}
            <div id="gsheets-oauth" class="mt-4 rounded-lg border border-slate-200 p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Client ID') }}</label>
                        <input type="text" name="oauth_client_id" value="{{ old('oauth_client_id', $config?->oauth_client_id) }}" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">
                            {{ __('Client secret') }}
                            @if ($hasOauthSecret)
                                <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700">{{ __('saved ✓') }}</span>
                            @endif
                        </label>
                        <input type="password" name="oauth_client_secret" class="input" autocomplete="new-password" placeholder="{{ $hasOauthSecret ? __('•••••••• (leave blank to keep)') : '' }}" />
                    </div>
                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="text-sm font-medium text-slate-700">
                            {{ __('Refresh token') }}
                            @if ($hasOauthRefreshToken)
                                <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700">{{ __('saved ✓') }}</span>
                            @endif
                        </label>
                        <input type="password" name="oauth_refresh_token" class="input" autocomplete="new-password" placeholder="{{ $hasOauthRefreshToken ? __('•••••••• (leave blank to keep)') : '' }}" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('The Google account that owns the refresh token must have edit access to the spreadsheet.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Spreadsheet --}}
            <div class="mt-4 rounded-lg border border-slate-200 p-4">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-slate-700">{{ __('Spreadsheet') }}</label>
                    <input type="text" name="spreadsheet_id" value="{{ old('spreadsheet_id', $config?->spreadsheet_id) }}" class="input" autocomplete="off" placeholder="{{ __('Paste the spreadsheet id or its full URL') }}" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('One tab per table is created and kept up to date.') }}</p>
                </div>
            </div>
        </section>

        {{-- Tabs --}}
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Tabs to mirror') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Turn a tab off to stop syncing that table. Existing data stays in the sheet.') }}</p>

            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($tables as $table)
                    <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2">
                        <span class="flex items-center gap-2">
                            <input type="checkbox" name="tabs[{{ $table['key'] }}]" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" {{ $table['enabled'] ? 'checked' : '' }} />
                            <span class="text-sm font-medium text-slate-800">{{ $table['tab'] }}</span>
                        </span>
                        <span class="text-xs text-slate-500">{{ trans_choice(':count column|:count columns', $table['columns'], ['count' => $table['columns']]) }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('Save Google Sheets settings') }}</button>
            <a href="{{ route('mess.settings.edit') }}" class="btn btn-secondary">{{ __('Back to mess settings') }}</a>
        </div>
    </form>

    {{-- Actions (fetch-driven) --}}
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Actions') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('Save your settings first — these actions use the stored credentials.') }}</p>

        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" class="btn btn-secondary" data-gsheets-action data-url="{{ route('mess.google-sheets.test') }}">{{ __('Test connection') }}</button>
            <button type="button" class="btn btn-secondary" data-gsheets-action data-url="{{ route('mess.google-sheets.backfill') }}">{{ __('Sync existing data') }}</button>
            <button type="button" class="btn btn-secondary" data-gsheets-action data-url="{{ route('mess.google-sheets.create-spreadsheet') }}">{{ __('Create spreadsheet') }}</button>
        </div>

        <p id="gsheets-result" class="mt-3 hidden text-sm"></p>
    </section>

    <script>
        (function () {
            var serviceBlock = document.getElementById('gsheets-service-account');
            var oauthBlock = document.getElementById('gsheets-oauth');

            function syncAuthBlocks() {
                var checked = document.querySelector('input[name="auth_mode"]:checked');
                var mode = checked ? checked.value : 'service_account';
                serviceBlock.classList.toggle('hidden', mode !== 'service_account');
                oauthBlock.classList.toggle('hidden', mode !== 'oauth');
            }

            document.querySelectorAll('input[name="auth_mode"]').forEach(function (radio) {
                radio.addEventListener('change', syncAuthBlocks);
            });
            syncAuthBlocks();

            var result = document.getElementById('gsheets-result');

            document.querySelectorAll('[data-gsheets-action]').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    var url = btn.getAttribute('data-url');
                    var original = btn.textContent;

                    btn.disabled = true;
                    btn.textContent = @json(__('Working…'));
                    result.className = 'mt-3 text-sm text-slate-500';
                    result.textContent = @json(__('Talking to Google…'));

                    try {
                        var resp = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });
                        var data = await resp.json();
                        result.className = 'mt-3 text-sm ' + (data.ok ? 'text-emerald-700' : 'text-rose-700');
                        result.textContent = data.message || '';
                    } catch (e) {
                        result.className = 'mt-3 text-sm text-rose-700';
                        result.textContent = e.message;
                    } finally {
                        btn.disabled = false;
                        btn.textContent = original;
                    }
                });
            });
        })();
    </script>
@endsection
