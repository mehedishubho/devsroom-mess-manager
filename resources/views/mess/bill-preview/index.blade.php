@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Bill preview') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Live calculation for the selected month. Updates within 1 hour of any change.') }}</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="year" class="rounded-md border-slate-300 text-sm">
                @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endfor
            </select>
            <select name="month" class="rounded-md border-slate-300 text-sm">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($month === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                @endfor
            </select>
            <button type="submit" class="inline-flex items-center rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">{{ __('Apply') }}</button>
        </form>
    </header>
    @include('mess.bill-preview._summary', ['preview' => $preview])
    @include('mess.bill-preview._row-cards', ['members' => $preview['members']])
@endsection