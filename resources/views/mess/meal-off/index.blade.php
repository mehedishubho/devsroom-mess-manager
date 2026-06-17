@extends('layouts.app')
@section('content')
    <header class="mb-4">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Meal off approval') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Review and approve or reject meal off requests.') }}</p>
    </header>

    @php
        $tabs = [
            ['key' => 'pending', 'label' => __('Pending (:n)', ['n' => $counts['pending'] ?? 0]), 'url' => route('mess.meal-off.index', ['tab' => 'pending'])],
            ['key' => 'approved', 'label' => __('Approved (:n)', ['n' => $counts['approved'] ?? 0]), 'url' => route('mess.meal-off.index', ['tab' => 'approved'])],
            ['key' => 'rejected', 'label' => __('Rejected (:n)', ['n' => $counts['rejected'] ?? 0]), 'url' => route('mess.meal-off.index', ['tab' => 'rejected'])],
        ];
    @endphp

    <x-tab-nav :tabs="$tabs" :active-key="$tab" class="mb-6" />

    <div class="space-y-3">
        @forelse ($requests as $req)
            @include('mess.meal-off._card', ['request' => $req])
        @empty
            <x-empty-state
                :title="__('No :status meal off requests.', ['status' => $tab])"
                :description="__('When members request meal off, they will appear here.')"
            />
        @endforelse
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
@endsection
