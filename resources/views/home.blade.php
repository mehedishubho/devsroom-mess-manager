@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">
            {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('Your mess is set up. You can edit mess details or add members from the sidebar.') }}
        </p>
    </header>

    <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Mess settings') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Update name, address, rent, meal values, and currency.') }}</p>
            <div class="mt-4">
                <a href="{{ route('mess.settings.edit') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 min-h-[44px]">
                    {{ __('Open mess settings') }}
                </a>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Members') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Invite a member by email. They will receive a link to set their password.') }}</p>
            <div class="mt-4">
                <a href="{{ route('mess.members.invite.create') }}" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50 min-h-[44px]">
                    {{ __('Add a member') }}
                </a>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Audit log') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Every change to mess data, recorded with the user, timestamp, and before/after values.') }}</p>
            <div class="mt-4">
                <a href="{{ route('mess.audit') }}" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50 min-h-[44px]">
                    {{ __('View audit log') }}
                </a>
            </div>
        </div>
    </div>
@endsection
