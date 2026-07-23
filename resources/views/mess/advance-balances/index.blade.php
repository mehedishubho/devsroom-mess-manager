@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Member balances') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Each member\'s current balance — a credit they hold or an amount they owe.') }}</p>
        <p class="mt-2 max-w-2xl text-xs text-slate-500">{{ __('Balances update automatically from payments and the monthly close. Use Adjust only for corrections or non-cash entries (e.g. a utility charge). For money a member hands you, record it on the Payments page.') }}</p>
    </header>
    @include('mess.advance-balances._list')
@endsection