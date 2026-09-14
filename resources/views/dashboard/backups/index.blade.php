@extends('layouts.app')

@php
    // Sort links preserve the current search + direction.
    $sortLink = function (string $column) use ($sort, $dir) {
        $next = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

        return route('dashboard.backups.index', array_merge(request()->query(), ['sort' => $column, 'dir' => $next]));
    };
    $sortArrow = function (string $column) use ($sort, $dir) {
        if ($sort !== $column) {
            return '';
        }

        return $dir === 'asc' ? ' ▲' : ' ▼';
    };
@endphp

@section('content')
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Backups') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Off-server backup and restore surface (super-admin only).') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form action="{{ route('dashboard.backups.run') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    {{ __('Backup now') }}
                </button>
            </form>
        </div>
    </header>

    {{-- At-a-glance health: the page used to be all tables, so "is this working?"
         took a careful read. These four numbers answer it immediately. --}}
    <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            :label="__('Last backup')"
            :value="$lastBackupAt ? $lastBackupAt->diffForHumans() : __('Never')"
            :hint="$lastBackupAt ? $lastBackupAt->format('Y-m-d H:i') : __('No successful backup recorded yet')"
        />
        <x-stat-card
            :label="__('Archives')"
            :value="number_format($totalCount)"
            :hint="__('On this server')"
        />
        <x-stat-card
            :label="__('Total size')"
            :value="number_format($totalSize / 1024 / 1024, 2).' MB'"
            :hint="__('Across all archives')"
        />
        <x-stat-card
            :label="__('Next run')"
            :value="$nextRunAt ? $nextRunAt->diffForHumans() : __('Off')"
            :hint="$nextRunAt ? $nextRunAt->format('Y-m-d H:i') : __('Automatic backups are disabled')"
        />
    </section>

    {{-- Scheduler-health banner: surfaces a missing/failing server cron (the #1
         reason "backups are configured but none appear"). Code cannot install
         the per-minute cron — that's operator-managed on the panel. --}}
    @if (! ($schedulerHealthy ?? true))
        <section class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 text-xl" aria-hidden="true">⚠️</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-900">{{ __('Automatic backups may not be running') }}</p>
                    <p class="mt-1 text-sm text-amber-800">{{ $schedulerIssue }}</p>
                    <p class="mt-3 text-xs font-medium uppercase tracking-wide text-amber-700">{{ __('Install this cron line on the server (crontab -e / your panel):') }}</p>
                    <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <code class="block flex-1 break-all rounded bg-amber-100 px-3 py-2 font-mono text-xs text-amber-900">{{ $schedulerCronLine }}</code>
                        <button type="button"
                                class="btn btn-secondary text-xs"
                                onclick="navigator.clipboard && navigator.clipboard.writeText({{ json_encode($schedulerCronLine) }}); this.textContent = '{{ __('Copied') }}';">
                            {{ __('Copy') }}
                        </button>
                    </div>
                        <p class="mt-2 text-xs text-amber-700">{{ __('Then click "Backup now" to confirm the mechanism works. If it fails, the Activity log below shows the reason.') }}</p>
                        <p class="mt-2 text-xs text-amber-700">{{ __('If "php" is not on the cron user\'s PATH (common on shared hosting), run "php artisan backup:install" on the server — it prints this exact line with the absolute PHP path.') }}</p>
                        <p class="mt-2 text-xs text-amber-700">{{ __('Deployed in a container? A host crontab cannot reach this path (:path) because it only exists inside the container. Run "php artisan schedule:work" as a long-lived service instead — no cron needed.', ['path' => base_path()]) }}</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- Queue-health banner: "Backup now" and restores are queued jobs. With no
             worker they sit in the jobs table and the activity row says "running"
             forever, which reads as "still working" when nothing is listening. --}}
        @if (! ($queueHealthy ?? true))
            <section class="mb-6 rounded-xl border border-rose-300 bg-rose-50 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-xl" aria-hidden="true">⛔</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-rose-900">{{ __('Background jobs are not being processed') }}</p>
                        <p class="mt-1 text-sm text-rose-800">{{ $queueIssue }}</p>
                        <p class="mt-3 text-xs font-medium uppercase tracking-wide text-rose-700">{{ __('Run a queue worker (or enable the queue service in your panel):') }}</p>
                        <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <code class="block flex-1 break-all rounded bg-rose-100 px-3 py-2 font-mono text-xs text-rose-900">cd {{ base_path() }} &amp;&amp; {{ $queueWorkerCommand }}</code>
                            <button type="button"
                                    class="btn btn-secondary text-xs"
                                    onclick="navigator.clipboard && navigator.clipboard.writeText({{ json_encode('cd '.base_path().' && '.$queueWorkerCommand) }}); this.textContent = '{{ __('Copied') }}';">
                                {{ __('Copy') }}
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-rose-700">{{ __('Until a worker is running, every "Backup now" and restore stays queued. On shared hosting with QUEUE_CONNECTION=sync jobs run inline, so this banner never appears.') }}</p>
                    </div>
                </div>
            </section>
        @endif

    {{-- Backup activity log (shown FIRST so a failed Backup now is immediately visible) --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Activity log') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Every backup / purge / monitor / restore attempt — failures show the real reason (e.g. mysqldump missing).') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('dashboard.backups.logs.export', array_filter(['log_action' => $logAction, 'log_status' => $logStatus])) }}" class="btn btn-secondary">{{ __('Export CSV') }}</a>
                @if ($backupLogs->isNotEmpty())
                    <form action="{{ route('dashboard.backups.logs.clear') }}" method="POST" onsubmit="return confirm('{{ __('Delete ALL log entries?') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary">{{ __('Clear all') }}</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Filters preserve the backup-list query so one refresh doesn't wipe the other. --}}
        <form method="GET" action="{{ route('dashboard.backups.index') }}" class="mt-4 flex flex-wrap items-end gap-2">
            @if ($search !== '')
                <input type="hidden" name="q" value="{{ $search }}" />
            @endif
            @if ($sort !== 'date' || $dir !== 'desc')
                <input type="hidden" name="sort" value="{{ $sort }}" />
                <input type="hidden" name="dir" value="{{ $dir }}" />
            @endif
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-slate-600">{{ __('Action') }}</span>
                <select name="log_action" class="input">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach ($logActions as $action)
                        <option value="{{ $action }}" @selected($logAction === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-slate-600">{{ __('Status') }}</span>
                <select name="log_status" class="input">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="success" @selected($logStatus === 'success')>{{ __('success') }}</option>
                    <option value="failure" @selected($logStatus === 'failure')>{{ __('failed') }}</option>
                </select>
            </label>
            <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
            @if ($logAction !== '' || $logStatus !== '')
                <a href="{{ route('dashboard.backups.index') }}" class="text-xs font-medium text-slate-600 hover:underline">{{ __('Reset') }}</a>
            @endif
        </form>

        @if (! empty($backupLogUnavailable))
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {{ __('Activity log is unavailable — the backup_logs table is missing. Run "php artisan migrate --force" on the server to enable it.') }}
            </p>
        @endif
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('When') }}</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Action') }}</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Message') }}</th>
                        <th scope="col" class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($backupLogs as $log)
                        <tr class="align-top">
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $log->created_at?->diffForHumans() }}</td>
                            <td class="px-3 py-2 text-sm font-medium text-slate-900">{{ $log->action }}</td>
                            <td class="px-3 py-2 text-sm">
                                @if ($log->status === 'success')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('success') }}</span>
                                @elseif ($log->status === 'running' && ($log->stale ?? false))
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800" title="{{ __('No queue worker picked this up') }}">{{ __('stuck') }}</span>
                                @elseif ($log->status === 'running')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('running') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">{{ __('failed') }}</span>
                                @endif
                            </td>
                            <td class="max-w-xl px-3 py-2 text-xs text-slate-600">
                                @if ($log->path)<span class="break-all font-mono text-slate-500">{{ basename($log->path) }}</span>@if ($log->message)<br />@endif @endif
                                @if ($log->message)<span class="break-words whitespace-pre-wrap">{{ $log->message }}</span>@endif
                                @if (! empty($log->hint))
                                    <p class="mt-1 rounded bg-amber-50 px-2 py-1 text-amber-900">
                                        <span class="font-semibold">{{ __('What to do:') }}</span> {{ $log->hint }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="{{ route('dashboard.backups.logs.destroy', $log) }}" class="inline" onsubmit="return confirm('{{ __('Delete this log entry?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-700 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">{{ __('No activity yet. Click "Backup now" to create a backup.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($backupLogs->hasPages())
            <div class="mt-4">{{ $backupLogs->links() }}</div>
        @endif
    </section>

    {{-- Schedule + retention + storage-provider toggles (Configure form, inline) --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Configuration') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Schedule, retention, storage providers and alerts. Changes take effect immediately.') }}</p>
        </div>
        @include('dashboard.backups._configure_form')
    </section>

    {{-- Disaster recovery + escape hatch. Both are deliberately outside the
         Configuration form so they can never be submitted by accident while
         changing a setting. --}}
    <section class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Restore from an uploaded archive') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Disaster recovery: upload a backup .zip (downloaded earlier, or taken from an off-site mirror) and restore it. The uploaded file is kept on the backups disk so it behaves like any other archive afterwards.') }}</p>
            <form method="POST" action="{{ route('dashboard.backups.restore.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-3"
                  onsubmit="return confirm('{{ __('This overwrites the live database and uploaded files. Continue?') }}');">
                @csrf
                <label class="block">
                    <span class="text-xs font-medium text-slate-700">{{ __('Backup archive (.zip)') }}</span>
                    <input type="file" name="file" accept=".zip,application/zip" required class="mt-1 block w-full text-sm text-slate-700" />
                    @error('file') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-700">{{ __('Type the mess name to confirm') }}</span>
                    <input type="text" name="mess_name" required autocomplete="off" class="input mt-1" placeholder="{{ \App\Http\Controllers\Backup\BackupController::activeMessName() }}" />
                    @error('mess_name') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                </label>
                <button type="submit" class="btn btn-secondary text-rose-700">{{ __('Upload and restore') }}</button>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Stuck in maintenance mode?') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('If a restore was interrupted and the site is showing the maintenance page, this forces the app back online. Restores normally exit maintenance mode on their own — this is the last resort.') }}</p>
            <form method="POST" action="{{ route('dashboard.backups.recover') }}" class="mt-4"
                  onsubmit="return confirm('{{ __('Force the app out of maintenance mode?') }}');">
                @csrf
                <button type="submit" class="btn btn-secondary">{{ __('Bring the app back online') }}</button>
            </form>
        </div>
    </section>

    {{-- Bulk delete lives OUTSIDE the table so the per-row forms are never
         nested; the checkboxes join it via the HTML form attribute. --}}
    <form id="backup-bulk-delete" method="POST" action="{{ route('dashboard.backups.bulk-delete') }}"
          onsubmit="return confirm('{{ __('Delete the selected backups from every destination? This cannot be undone.') }}');">
        @csrf
        @method('DELETE')
    </form>

    {{-- Backup list (download / verify / restore / delete) --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 md:px-6">
            <div>
                <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Backups') }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ __(':count archive(s)', ['count' => number_format($totalCount)]) }}
                    · {{ number_format($totalSize / 1024 / 1024, 2) }} MB
                    @if ($search !== '')
                        · {{ __(':count match ":term"', ['count' => number_format($backups->total()), 'term' => $search]) }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('dashboard.backups.index') }}" class="flex items-center gap-2">
                    <input type="search" name="q" value="{{ $search }}" class="input" placeholder="{{ __('Search filename…') }}" aria-label="{{ __('Search backups') }}" />
                    @if ($sort !== 'date' || $dir !== 'desc')
                        <input type="hidden" name="sort" value="{{ $sort }}" />
                        <input type="hidden" name="dir" value="{{ $dir }}" />
                    @endif
                    <button type="submit" class="btn btn-secondary">{{ __('Search') }}</button>
                </form>
                @if ($backups->isNotEmpty())
                    <button type="submit" form="backup-bulk-delete" class="btn btn-secondary text-rose-700">{{ __('Delete selected') }}</button>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="w-10 px-4 py-3">
                            <input type="checkbox" data-backup-select-all class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" aria-label="{{ __('Select all backups') }}" />
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                            <a href="{{ $sortLink('name') }}" class="hover:underline">{{ __('File') }}{{ $sortArrow('name') }}</a>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                            <a href="{{ $sortLink('size') }}" class="hover:underline">{{ __('Size') }}{{ $sortArrow('size') }}</a>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                            <a href="{{ $sortLink('date') }}" class="hover:underline">{{ __('Created') }}{{ $sortArrow('date') }}</a>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Destinations') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Checksum') }}</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($backups as $backup)
                        <tr class="transition-colors hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <input type="checkbox" form="backup-bulk-delete" name="paths[]" value="{{ $backup['path'] }}"
                                       data-backup-select class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                       aria-label="{{ __('Select :name', ['name' => $backup['name']]) }}" />
                            </td>
                            <td class="break-all px-4 py-3 text-sm text-slate-900">{{ $backup['name'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ number_format($backup['size'] / 1024 / 1024, 2) }} MB</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($backup['destinations'] as $diskName => $present)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $present ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}"
                                              title="{{ $present ? __('Present on :disk', ['disk' => $diskName]) : __('Missing from :disk', ['disk' => $diskName]) }}">
                                            {{ $diskName }} {{ $present ? '✓' : '✗' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                @if ($backup['checksum'])
                                    <button type="button"
                                            class="break-all text-left font-mono hover:text-slate-700"
                                            title="{{ $backup['checksum'] }}"
                                            onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $backup['checksum'] }}'); this.textContent = '{{ __('Copied') }}';">
                                        {{ substr($backup['checksum'], 0, 12) }}…
                                    </button>
                                @else
                                    <span class="text-slate-400">{{ __('not computed') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <a href="{{ route('dashboard.backups.download', ['path' => $backup['path']]) }}"
                                       class="text-xs font-medium text-emerald-700 hover:underline">
                                        {{ __('Download') }}
                                    </a>
                                    <form method="POST" action="{{ route('dashboard.backups.verify') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="path" value="{{ $backup['path'] }}" />
                                        <button type="submit" class="text-xs font-medium text-sky-700 hover:underline">
                                            {{ $backup['checksum'] ? __('Verify') : __('Compute checksum') }}
                                        </button>
                                    </form>
                                    <a href="{{ route('dashboard.backups.restore.show', ['path' => $backup['path']]) }}"
                                       class="text-xs font-medium text-amber-700 hover:underline">
                                        {{ __('Restore') }}
                                    </a>
                                    <form method="POST" action="{{ route('dashboard.backups.destroy') }}" class="inline" onsubmit="return confirm('{{ __('Delete this backup from every destination? This cannot be undone.') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="path" value="{{ $backup['path'] }}" />
                                        <button type="submit" class="text-xs font-medium text-red-700 hover:underline">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                                @if ($search !== '')
                                    {{ __('No backups match ":term".', ['term' => $search]) }}
                                @else
                                    {{ __('No backups yet. Click "Backup now" to create one.') }}
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($backups->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 md:px-6">{{ $backups->links() }}</div>
        @endif
    </section>

    @once
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var all = document.querySelector('[data-backup-select-all]');
                if (!all) return;
                all.addEventListener('change', function () {
                    document.querySelectorAll('[data-backup-select]').forEach(function (box) {
                        box.checked = all.checked;
                    });
                });
            });
        </script>
    @endonce
@endsection
