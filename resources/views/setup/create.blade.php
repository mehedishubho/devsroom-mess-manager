<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Initial setup') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <p class="text-sm font-medium text-emerald-700">{{ config('app.name') }}</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-950">{{ __('Initial setup') }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ __('Create the first administrator account to secure this installation.') }}</p>
            </div>

            @if ($errors->any())
                <div role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ __('Please correct the highlighted fields and try again.') }}
                </div>
            @endif

            <form method="POST" action="{{ route('setup.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-slate-700">{{ __('Full Name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-700">{{ __('Password Confirmation') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </div>

                <button type="submit" class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    {{ __('Create administrator') }}
                </button>
            </form>
        </section>
    </main>
</body>
</html>
