@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Add a member') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Send an invite by email. The member will receive a link to set their password.') }}</p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.members.invite.store') }}" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium text-slate-900">
                    {{ __('Member email') }}<span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <p class="text-xs text-slate-500">{{ __('The invitation link expires in 24 hours') }}</p>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                    class="input">
                @error('email') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ __('Send invitation') }}
                </button>
                <a href="{{ route('home') }}" class="btn btn-ghost">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
