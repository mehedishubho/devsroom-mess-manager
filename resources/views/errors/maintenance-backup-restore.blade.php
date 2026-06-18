@extends('errors::minimal')

@section('title', __('Restore in progress'))

@section('content')
    <div class="p-8 text-center">
        <h1 class="text-2xl font-bold mb-2">{{ __('Restore in progress') }}</h1>
        <p class="text-gray-600">
            {{ __('A backup restore is running. The app will be back shortly.') }}
        </p>
    </div>
@endsection
