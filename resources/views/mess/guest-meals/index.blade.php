@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Guest meals') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('All guest meals recorded across members.') }}</p>
        </div>
        <a href="{{ route('mess.guest-meals.create') }}" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            {{ __('Add guest meal') }}
        </a>
    </header>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Date') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Host') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Guest') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Meal') }}</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Qty') }}</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Charge') }}</th>
                    <th scope="col" class="relative px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($guestMeals as $gm)
                    <tr>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $gm->date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-900">{{ $gm->member?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-900">{{ $gm->guest_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($gm->meal_type) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-600">{{ $gm->quantity }}</td>
                        <td class="px-4 py-3 text-right text-sm font-medium text-slate-900">{{ number_format((float) $gm->charge_amount, 2) }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('mess.guest-meals.edit', $gm) }}" class="text-emerald-700 hover:underline">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-600">
                            {{ __('No guest meals recorded yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $guestMeals->links() }}</div>
@endsection
