<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-emerald-600 focus:px-3 focus:py-2 focus:text-white">
        {{ __('Skip to main content') }}
    </a>

    <div class="flex min-h-screen flex-col">
        <header class="flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6">
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-ghost md:hidden" data-sidebar-toggle aria-label="{{ __('Open menu') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>
                <span class="text-base font-semibold text-slate-900">{{ config('app.name') }}</span>
            </div>
            <div class="flex items-center gap-3">
                <x-notification-bell />
                <span class="hidden text-sm text-slate-600 sm:inline">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost" aria-label="{{ __('Log out') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                        </svg>
                        <span class="hidden sm:inline">{{ __('Log out') }}</span>
                    </button>
                </form>
            </div>
        </header>

        <div class="flex flex-1">
            <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white transition-transform md:static md:translate-x-0" data-sidebar>
                <x-sidebar />
            </aside>

            <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-slate-900/50 md:hidden"></div>

            <main id="main-content" class="flex-1 px-4 py-6 md:px-8 md:py-8">
                <div class="mx-auto w-full max-w-384">
                    @if (session('success'))
                        <div role="alert" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @once
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const toggle = document.querySelector('[data-sidebar-toggle]');
                const sidebar = document.querySelector('[data-sidebar]');
                const backdrop = document.querySelector('[data-sidebar-backdrop]');
                if (!toggle || !sidebar || !backdrop) return;

                function open() { sidebar.classList.remove('-translate-x-full'); backdrop.classList.remove('hidden'); }
                function close() { sidebar.classList.add('-translate-x-full'); backdrop.classList.add('hidden'); }
                toggle.addEventListener('click', open);
                backdrop.addEventListener('click', close);
            });
        </script>
    @endonce
</body>
</html>
