@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Close month') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Snapshot the mess for :label.', ['label' => \Carbon\Carbon::create($year, $month, 1)->format('F Y')]) }}</p>
        </div>
        <x-month-nav :year="$year" :month="$month" route="mess.close.index" />
    </header>

    @include('mess.bill-preview._summary')

    @if ($closeQueued)
        <div role="alert" class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            {{ __('A month close has been dispatched and is waiting to be processed. Keep the queue worker running (composer run dev) until it completes.') }}
        </div>
    @endif

    @if ($isClosed)
        @php
            $closing = \App\Models\MonthlyClosing::query()->where('year', $year)->where('month', $month)->first();
        @endphp
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            <span>{{ __('This month is already closed.') }}</span>
            @if ($closing)
                <a href="{{ route('mess.closings.show', $closing) }}" class="font-semibold text-amber-900 underline">{{ __('View the closing') }}</a>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('mess.close.trigger') }}" class="mt-4" x-data="{ open: false }">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}" />
            <input type="hidden" name="month" value="{{ $month }}" />
            <button type="button" @click="open = true" class="btn btn-primary">
                {{ __('Close :label', ['label' => \Carbon\Carbon::create($year, $month, 1)->format('F Y')]) }}
            </button>
            @include('mess.close._confirmation-modal')
        </form>
    @endif
@endsection
