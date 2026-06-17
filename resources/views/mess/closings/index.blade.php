@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Month closings') }}</h1>
    </header>
    @if ($closings->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            {{ __('No months have been closed yet.') }}
        </p>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3">{{ __('Period') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Total bazar') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Meal rate') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Members') }}</th>
                        <th class="px-4 py-3">{{ __('Closed at') }}</th>
                        <th class="px-4 py-3">{{ __('Closed by') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($closings as $c)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3"><a href="{{ route('mess.closings.show', $c) }}" class="text-emerald-700 hover:underline">{{ \Carbon\Carbon::create($c->year, $c->month, 1)->format('F Y') }}</a></td>
                            <td class="px-4 py-3 text-right">{{ \App\Support\Money::taka($c->total_bazar) }}</td>
                            <td class="px-4 py-3 text-right">{{ \App\Support\Money::taka($c->meal_rate) }}</td>
                            <td class="px-4 py-3 text-right">{{ $c->member_count }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $c->closed_at?->format('d-m-Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $c->closedBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $closings->links() }}</div>
    @endif
@endsection
