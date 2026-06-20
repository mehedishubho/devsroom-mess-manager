@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Edit payment') }}</h1>
    </header>
    <form method="POST" action="{{ route('mess.payments.update', $payment) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        @include('mess.payments._form')
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('mess.payments.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
@endsection