@extends('layouts.app')
@section('content')
    <header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ $member->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('Member profile') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mess.members.edit', $member) }}" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">
                {{ __('Edit') }}
            </a>
            <form method="POST" action="{{ route('mess.members.deactivate', $member) }}" class="inline" onsubmit="return confirm('{{ __('Mark this member as inactive? They will not appear in the daily meal grid.') }}');">
                @csrf
                @method('PATCH')
                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ __('Deactivate') }}
                </button>
            </form>
        </div>
    </header>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            @if ($member->photo_path)
                <img src="{{ Storage::disk('public')->url($member->photo_path) }}" alt="" class="h-24 w-24 rounded-full object-cover" />
            @else
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100 text-2xl font-semibold text-emerald-700">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</div>
            @endif
            <div class="flex-1">
                <h2 class="text-lg font-semibold text-slate-900">{{ $member->name }} <x-status-pill :variant="$member->status" class="ml-2" /></h2>
                @if ($member->room_or_seat)
                    <p class="text-sm text-slate-600">{{ $member->room_or_seat }}</p>
                @endif
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Mobile') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $member->mobile ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Email') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $member->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Profession') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $member->profession ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Joining date') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ optional($member->joining_date)->format('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Leaving date') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ optional($member->leaving_date)->format('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('NID') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $member->nid ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Emergency contact') }}</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $member->emergency_contact ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <section class="mt-6">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Recent meals (last 30 days)') }}</h2>
        <div class="mt-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            @if ($member->mealEntries->isEmpty())
                <p class="text-sm text-slate-600">{{ __('No meals recorded in the last 30 days.') }}</p>
            @else
                <ul class="divide-y divide-slate-200">
                    @foreach ($member->mealEntries as $entry)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-slate-600">{{ $entry->date->format('d M Y, l') }}</span>
                            <span class="text-slate-900">
                                @php
                                    $meals = collect([
                                        $entry->breakfast ? 'B' : null,
                                        $entry->lunch ? 'L' : null,
                                        $entry->dinner ? 'D' : null,
                                    ])->filter()->implode(', ');
                                @endphp
                                {{ $meals ?: '—' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="mt-6">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Request meal off for :name', ['name' => $member->name]) }}</h2>
        <form method="POST" action="{{ route('mess.members.meal-off.store', $member) }}" class="mt-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            @csrf
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="from_date" class="text-sm font-medium text-slate-900">{{ __('From date') }}<span class="text-red-600" aria-hidden="true">*</span></label>
                    <input type="date" name="from_date" id="from_date" required
                        class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="to_date" class="text-sm font-medium text-slate-900">{{ __('To date') }}<span class="text-red-600" aria-hidden="true">*</span></label>
                    <input type="date" name="to_date" id="to_date" required
                        class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>
            </div>
            <div class="mt-3 flex flex-col gap-1">
                <label for="reason" class="text-sm font-medium text-slate-900">{{ __('Reason') }}<span class="text-red-600" aria-hidden="true">*</span></label>
                <textarea name="reason" id="reason" rows="2" required minlength="3" maxlength="500"
                    class="min-h-[60px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600"></textarea>
            </div>
            <div class="mt-3">
                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    {{ __('Submit request') }}
                </button>
            </div>
        </form>
    </section>
@endsection
