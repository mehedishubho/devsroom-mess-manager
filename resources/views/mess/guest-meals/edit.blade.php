@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Edit guest meal') }}</h1>
    </header>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.guest-meals.update', $guestMeal) }}">
            @include('mess.guest-meals._form', ['guestMeal' => $guestMeal, 'method' => 'PATCH'])
        </form>
    </div>
@endsection
