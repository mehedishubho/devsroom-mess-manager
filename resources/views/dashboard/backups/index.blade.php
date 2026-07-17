@extends('layouts.app')

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
            <form action="{{ route('dashboard.backups.restore-test.run') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    {{ __('Run restore-test') }}
                </button>
            </form>
        </div>
    </header>

    {{-- Backup activity log (shown FIRST so a failed Backup now is immediately visible) --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Activity log') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Every backup / restore / configure attempt — failures show the real reason (e.g. mysqldump missing).') }}</p>
            </div>
            @if ($backupLogs->isNotEmpty())
                <form action="{{ route('dashboard.backups.logs.clear') }}" method="POST" onsubmit="return confirm('{{ __('Delete ALL log entries?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary">{{ __('Clear all') }}</button>
                </form>
            @endif
        </div>
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
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">{{ __('failed') }}</span>
                                @endif
                            </td>
                            <td class="max-w-xl px-3 py-2 text-xs text-slate-600">
                                @if ($log->path)<span class="break-all font-mono text-slate-500">{{ basename($log->path) }}</span>@if ($log->message)<br />@endif @endif
                                @if ($log->message)<span class="break-words whitespace-pre-wrap">{{ $log->message }}</span>@endif
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
    </section>

    {{-- Schedule + retention + storage-provider toggles (Configure form, inline) --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Configuration') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Schedule, retention, and storage providers. Changes take effect immediately.') }}</p>
        </div>
        @include('dashboard.backups._configure_form')
    </section>

    {{-- Health badge (D-04) --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Restore-test health') }}</h2>
        <div class="mt-3">
            @include('dashboard.backups._health_badge', ['latestRestoreTest' => $latestRestoreTest])
        </div>
    </section>

    {{-- Backup list (download / restore / delete) --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3 md:px-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Backups') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Path') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Size') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Last modified') }}</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($backups as $backup)
                        <tr class="transition-colors hover:bg-slate-50">
                            <td class="break-all px-4 py-3 text-sm text-slate-900">{{ basename($backup['path']) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ number_format($backup['size'] / 1024 / 1024, 2) }} MB</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <a href="{{ route('dashboard.backups.download', ['path' => $backup['path']]) }}"
                                       class="text-xs font-medium text-emerald-700 hover:underline">
                                        {{ __('Download') }}
                                    </a>
                                    <a href="{{ route('dashboard.backups.restore.show', ['path' => $backup['path']]) }}"
                                       class="text-xs font-medium text-amber-700 hover:underline">
                                        {{ __('Restore') }}
                                    </a>
                                    <form method="POST" action="{{ route('dashboard.backups.destroy', ['path' => $backup['path']]) }}" class="inline" onsubmit="return confirm('{{ __('Delete this backup? This cannot be undone.') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-700 hover:underline">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                {{ __('No backups yet. Click "Backup now" to create one.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
