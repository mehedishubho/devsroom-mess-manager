@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Corrections for :label', ['label' => \Carbon\Carbon::create($closing->year, $closing->month, 1)->format('F Y')]) }}</h1>
        </div>
        <a href="{{ route('mess.closings.corrections.create', $closing) }}" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">{{ __('Add correction') }}</a>
    </header>
    @if ($closing->corrections->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            {{ __('No corrections recorded.') }}
        </p>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Member') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                        <th class="px-4 py-3">{{ __('Applied to') }}</th>
                        <th class="px-4 py-3">{{ __('Reason') }}</th>
                        <th class="px-4 py-3">{{ __('Entered by') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($closing->corrections as $c)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ $c->created_at->format('d-m-Y') }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ $c->member?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ (float) $c->amount >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ \App\Support\Money::taka($c->amount) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ \Carbon\Carbon::create($c->applied_to_year, $c->applied_to_month, 1)->format('F Y') }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $c->reason }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $c->enteredBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
