<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('My home') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-3xl px-4 py-8 md:px-8">
        <header class="mb-6">
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">
                {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                {{ __('This is your personal space. Your profile, meal history, and bill will appear here in later phases.') }}
            </p>
        </header>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">
                {{ __('My profile') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ __('View and update your name, mobile, and contact info.') }}
            </p>
            <p class="mt-4 text-sm text-amber-800">
                {{ __('Profile UI is coming in a later phase.') }}
            </p>
        </div>
    </main>
</body>
</html>
