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
                <button type="button" class="touch-target rounded-md p-2 text-slate-700 hover:bg-slate-100 md:hidden" data-sidebar-toggle aria-label="{{ __('Open menu') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>
                <span class="text-base font-semibold text-slate-900">{{ config('app.name') }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-slate-600 sm:inline">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="touch-target inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100" aria-label="{{ __('Log out') }}">
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
                <nav class="flex h-full flex-col gap-1 p-4">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition min-h-[44px] {{ request()->routeIs('home') ? 'bg-emerald-50 text-emerald-700 border-l-2 border-emerald-600' : 'text-slate-700 hover:bg-slate-100 border-l-2 border-transparent' }}" @if (request()->routeIs('home')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                        </svg>
                        <span>{{ __('Home') }}</span>
                    </a>
                    <a href="{{ route('mess.settings.edit') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition min-h-[44px] {{ request()->routeIs('mess.settings.*') ? 'bg-emerald-50 text-emerald-700 border-l-2 border-emerald-600' : 'text-slate-700 hover:bg-slate-100 border-l-2 border-transparent' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <span>{{ __('Mess settings') }}</span>
                    </a>
                    <a href="{{ route('mess.audit') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition min-h-[44px] {{ request()->routeIs('mess.audit') ? 'bg-emerald-50 text-emerald-700 border-l-2 border-emerald-600' : 'text-slate-700 hover:bg-slate-100 border-l-2 border-transparent' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                        </svg>
                        <span>{{ __('Audit log') }}</span>
                    </a>
                    <a href="{{ route('mess.members.invite.create') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition min-h-[44px] {{ request()->routeIs('mess.members.invite.*') ? 'bg-emerald-50 text-emerald-700 border-l-2 border-emerald-600' : 'text-slate-700 hover:bg-slate-100 border-l-2 border-transparent' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <span>{{ __('Add member') }}</span>
                    </a>
                </nav>
            </aside>

            <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-slate-900/50 md:hidden"></div>

            <main id="main-content" class="flex-1 px-4 py-6 md:px-8 md:py-8">
                <div class="mx-auto max-w-3xl">
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
