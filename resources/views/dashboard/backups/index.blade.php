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

    {{-- Configuration details --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Configuration') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Where backups are stored, how often they run, and how long they are kept.') }}</p>
            </div>
            <a href="{{ route('dashboard.backups.configure') }}" class="btn btn-primary">
                {{ __('Configure') }}
            </a>
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Local destination') }}</dt>
                <dd class="mt-1 text-sm font-medium text-emerald-700">{{ __('Local — default') }} ✓</dd>
                <dd class="text-xs text-slate-500">storage/app/backups</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Off-server mirror') }}</dt>
                @if ($spacesConfigured)
                    <dd class="mt-1 text-sm font-medium text-emerald-700">{{ __('Spaces — configured') }} ✓</dd>
                    <dd class="text-xs text-slate-500">{{ config('filesystems.disks.backups.bucket') }} · {{ config('filesystems.disks.backups.region') }}</dd>
                @else
                    <dd class="mt-1 text-sm font-medium text-slate-500">{{ __('Spaces — not configured') }}</dd>
                    <dd class="text-xs text-slate-500">{{ __('Backups stay local only.') }}</dd>
                @endif
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Schedule') }}</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900">{{ $config->scheduleLabel() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Retention') }}</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900">{{ __('Keep all :n days', ['n' => $config->keep_all_days]) }}</dd>
                <dd class="text-xs text-slate-500">{{ __('Cap :n MB', ['n' => number_format($config->max_mb)]) }}</dd>
            </div>
        </dl>
    </section>

    {{-- Health badge (D-04) --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Restore-test health') }}</h2>
        <div class="mt-3">
            @include('dashboard.backups._health_badge', ['latestRestoreTest' => $latestRestoreTest])
        </div>
    </section>

    {{-- Backup list --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
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
