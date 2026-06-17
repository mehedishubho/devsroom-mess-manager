@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Add guest meal') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Record a meal for a non-member guest.') }}</p>
    </header>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.guest-meals.store') }}">
            @include('mess.guest-meals._form', ['guestMeal' => $guestMeal, 'method' => 'POST'])
        </form>
    </div>
@endsection
