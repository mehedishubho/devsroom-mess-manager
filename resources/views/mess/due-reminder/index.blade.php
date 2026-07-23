@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Due reminders') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Send an in-app reminder to every member who currently owes money.') }}</p>
    </header>
    <form method="POST" action="{{ route('mess.due-reminder.send') }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        @csrf
        @if ($members->isEmpty())
            <p class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
                {{ __('No members currently have a due balance.') }}
            </p>
        @else
            <ul class="divide-y divide-slate-200">
                @foreach ($members as $member)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <label class="flex items-center gap-3 text-sm text-slate-800">
                            <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" checked class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span class="font-medium text-slate-900">{{ $member->name }}</span>
                            @if ($member->mobile)
                                <span class="text-xs text-slate-500">{{ $member->mobile }}</span>
                            @endif
                        </label>
                        @php $net = $member->advanceBalance?->netBalance() ?? 0; @endphp
                        <span class="text-sm font-semibold text-rose-700">{{ __('Owes') }} {{ \App\Support\Money::taka(abs($net)) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">
                    {{ __('Send reminders') }}
                </button>
            </div>
        @endif
    </form>
@endsection
