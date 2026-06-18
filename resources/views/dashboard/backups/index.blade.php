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
                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    {{ __('Backup now') }}
                </button>
            </form>
            <form action="{{ route('dashboard.backups.restore-test.run') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    {{ __('Run restore-test') }}
                </button>
            </form>
        </div>
    </header>

    {{-- Health badge (D-04) --}}
    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Restore-test health') }}</h2>
        <div class="mt-3">
            @include('dashboard.backups._health_badge', ['latestRestoreTest' => $latestRestoreTest])
        </div>
    </section>

    {{-- Backup list --}}
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
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
                        <tr>
                            <td class="break-all px-4 py-3 text-sm text-slate-900">{{ basename($backup['path']) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ number_format($backup['size'] / 1024 / 1024, 2) }} MB</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-2">
                                    <a href="{{ route('dashboard.backups.download', ['path' => $backup['path']]) }}"
                                       class="inline-flex min-h-[44px] items-center justify-center rounded-md px-2 py-1 text-xs font-medium text-emerald-700 hover:underline">
                                        {{ __('Download') }}
                                    </a>
                                    <a href="{{ route('dashboard.backups.restore.show', ['path' => $backup['path']]) }}"
                                       class="inline-flex min-h-[44px] items-center justify-center rounded-md px-2 py-1 text-xs font-medium text-red-700 hover:underline">
                                        {{ __('Restore') }}
                                    </a>
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
