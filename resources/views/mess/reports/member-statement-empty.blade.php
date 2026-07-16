@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Member statement') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Select a member to view their statement.') }}</p>
    </header>

    <x-empty-state
        :title="__('No active members yet')"
        :description="__('Once you add an active member to this mess, their statement will be reachable from this page and from the sidebar link.')" />
@endsection
