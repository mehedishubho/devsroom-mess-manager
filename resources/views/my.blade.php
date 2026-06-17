@extends('layouts.app')
@section('content')
    @if (! $member)
        @include('my.no-member')
    @else
        <header class="mb-4">
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">
                {{ __('Hi, :name!', ['name' => $member->name]) }}
            </h1>
        </header>

        @php
            $tabs = [
                ['key' => 'profile', 'label' => __('Profile'), 'url' => route('my', ['tab' => 'profile'])],
                ['key' => 'meal-off', 'label' => __('Meal off'), 'url' => route('my', ['tab' => 'meal-off'])],
                ['key' => 'meals', 'label' => __('My meals'), 'url' => route('my', ['tab' => 'meals'])],
                ['key' => 'payments', 'label' => __('Payments'), 'url' => route('my', ['tab' => 'payments'])],
            ];
        @endphp

        <x-tab-nav :tabs="$tabs" :active-key="$tab" class="mb-6" />

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            @if ($tab === 'profile')
                @include('my._profile', ['member' => $member])
            @elseif ($tab === 'meal-off')
                @include('my._meal-off', ['member' => $member, 'mealOffRequests' => $mealOffRequests ?? collect()])
            @elseif ($tab === 'meals')
                @include('my._meals', ['member' => $member, 'mealEntries' => $mealEntries ?? collect()])
            @elseif ($tab === 'payments')
                @include('my._payments', ['payments' => $payments ?? collect()])
            @elseif ($tab === 'balance')
                @include('my._advance-balance', ['member' => $member])
            @elseif ($tab === 'bill-preview')
                @php
                    $billPreview = app(\App\Services\BillPreviewService::class);
                    $billRow = $billPreview->forMember($member->id, (int) now()->year, (int) now()->month);
                @endphp
                @include('my._bill-preview', ['row' => $billRow, 'member' => $member, 'year' => (int) now()->year, 'month' => (int) now()->month])
            @endif
        </div>
    @endif
@endsection
ion
