@extends('layouts.app')
@section('content')
    <div class="mx-auto max-w-lg">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Set your password') }}</h1>
            <p class="mt-2 text-sm text-slate-600">
                @if (auth()->user()->password_changed_at === null)
                    {{ __('Welcome! Please set a new password to activate your account.') }}
                @else
                    {{ __('Change your password.') }}
                @endif
            </p>

            <form method="POST" action="{{ route('my.password.change.store') }}" class="mt-6 flex flex-col gap-4">
                @csrf

                @if (auth()->user()->password_changed_at !== null)
                    <div class="flex flex-col gap-1">
                        <label for="current_password" class="text-sm font-medium text-slate-900">
                            {{ __('Current password') }}<span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input type="password" name="current_password" id="current_password" required
                            class="input @error('current_password') border-red-500 @enderror">
                        @error('current_password') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-medium text-slate-900">
                        {{ __('New password') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input type="password" name="password" id="password" required minlength="8"
                        class="input @error('password') border-red-500 @enderror">
                    @error('password') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="password_confirmation" class="text-sm font-medium text-slate-900">
                        {{ __('Confirm new password') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8"
                        class="input">
                </div>

                <div class="mt-2">
                    <button type="submit" class="btn btn-primary w-full">
                        {{ __('Save password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
