<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Set your password') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-md px-4 py-12">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Set your password') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Welcome to :app. Choose a password to activate your account.', ['app' => config('app.name')]) }}</p>

            <form method="POST" action="{{ route('password.set.update') }}" class="mt-6 flex flex-col gap-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-medium text-slate-900">
                        {{ __('Password') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <p class="text-xs text-slate-500">{{ __('At least 8 characters') }}</p>
                    <input type="password" name="password" id="password" required autofocus
                        class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    @error('password') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="password_confirmation" class="text-sm font-medium text-slate-900">
                        {{ __('Confirm password') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                        class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 min-h-[44px]">
                    {{ __('Set password') }}
                </button>
            </form>
        </div>
    </main>
</body>
</html>
