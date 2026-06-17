@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Advance balances') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Credit and debt carried by each member.') }}</p>
    </header>
    @include('mess.advance-balances._list')
@endsection