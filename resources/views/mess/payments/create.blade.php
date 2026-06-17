@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Record payment') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Record a bill payment or advance deposit for a member.') }}</p>
    </header>
    <form method="POST" action="{{ route('mess.payments.store') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        @include('mess.payments._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('mess.payments.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Cancel') }}</a>
            <button type="submit" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">{{ __('Save') }}</button>
        </div>
    </form>
@endsection