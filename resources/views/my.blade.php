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
                ['key' => 'overview', 'label' => __('Overview'), 'url' => route('my', ['tab' => 'overview'])],
                ['key' => 'profile', 'label' => __('Profile'), 'url' => route('my', ['tab' => 'profile'])],
                ['key' => 'meals', 'label' => __('My meals'), 'url' => route('my', ['tab' => 'meals'])],
                ['key' => 'meal-off', 'label' => __('Meal off'), 'url' => route('my', ['tab' => 'meal-off'])],
                ['key' => 'payments', 'label' => __('Payments'), 'url' => route('my', ['tab' => 'payments'])],
                ['key' => 'reports', 'label' => __('My reports'), 'url' => route('my', ['tab' => 'reports'])],
            ];
        @endphp

        <x-tab-nav :tabs="$tabs" :active-key="$tab" class="mb-6" />

        @if ($tab === 'overview')
            @include('my._overview', ['overview' => $overview ?? null])
        @elseif ($tab === 'reports')
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <a href="{{ route('my.reports.statement') }}"
                   class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-400 hover:shadow">
                    <h2 class="text-base font-semibold text-slate-900">{{ __('My Member Statement') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ __('Your meals, guest meals, payments, and closing bill for any month.') }}
                    </p>
                    <span class="mt-3 inline-flex min-h-[44px] items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                        {{ __('Open') }}
                    </span>
                </a>
                <a href="{{ route('my.reports.monthly') }}"
                   class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-400 hover:shadow">
                    <h2 class="text-base font-semibold text-slate-900">{{ __('Mess Monthly Report') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ __('Aggregate totals, meal rate, and bazar/fixed split for the mess (totals only).') }}
                    </p>
                    <span class="mt-3 inline-flex min-h-[44px] items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                        {{ __('Open') }}
                    </span>
                </a>
            </section>
        @else
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
    @endif
@endsection
