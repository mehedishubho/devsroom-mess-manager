@extends('layouts.app')
@section('content')
    @php
        $net = $member->advanceBalance?->netBalance() ?? 0;
    @endphp
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Adjust balance') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ $member->name }} —
            @if ($net > 0)
                <span class="font-semibold text-emerald-700">{{ __('current credit') }}: {{ \App\Support\Money::taka($net) }}</span>
            @elseif ($net < 0)
                <span class="font-semibold text-rose-700">{{ __('currently owes') }}: {{ \App\Support\Money::taka(abs($net)) }}</span>
            @else
                <span class="font-semibold text-slate-700">{{ __('currently settled') }}</span>
            @endif
        </p>
        <p class="mt-2 max-w-2xl text-xs text-slate-500">{{ __('Use this to correct a balance — record a non-cash credit, add a charge a member owes, or fix a past mistake. For real money a member hands you, use the Payments page instead.') }}</p>
    </header>

    <form method="POST" action="{{ route('mess.advance-balances.storeAdjust', $member) }}"
          class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"
          x-data="{ dir: 'credit', mag: '' }">
        @csrf
        {{-- The backend expects a signed `amount` (+credit / −charge). Build it from the
             chosen direction + a positive magnitude so the admin never has to type a sign. --}}
        <input type="hidden" name="amount" :value="dir === 'credit' ? (mag || 0) : -(mag || 0)" />

        <div>
            <span class="block text-sm font-medium text-slate-700">{{ __('What are you recording?') }}</span>
            <div class="mt-1 inline-flex rounded-md border border-slate-300 bg-white p-0.5 text-sm">
                <label class="cursor-pointer">
                    <input type="radio" name="dir" value="credit" x-model="dir" class="peer sr-only" />
                    <span class="inline-flex min-h-[44px] items-center rounded px-4 peer-checked:bg-emerald-600 peer-checked:text-white">{{ __('Add credit') }}</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="dir" value="charge" x-model="dir" class="peer sr-only" />
                    <span class="inline-flex min-h-[44px] items-center rounded px-4 peer-checked:bg-rose-600 peer-checked:text-white">{{ __('Add charge (they owe)') }}</span>
                </label>
            </div>
            <p class="mt-1 text-xs text-emerald-700" x-show="dir === 'credit'">{{ __('Increases the member\'s credit.') }}</p>
            <p class="mt-1 text-xs text-rose-700" x-show="dir === 'charge'">{{ __('Adds to what the member owes.') }}</p>
        </div>

        <div>
            <label for="mag" class="block text-sm font-medium text-slate-700">{{ __('Amount (BDT)') }}</label>
            <input type="number" id="mag" x-model.number="mag" min="0.01" step="0.01" required
                class="input mt-1" placeholder="0.00" />
        </div>

        <div>
            <label for="reason" class="block text-sm font-medium text-slate-700">{{ __('Reason') }} <span class="text-xs text-slate-500">({{ __('required — logged for audit') }})</span></label>
            <textarea name="reason" id="reason" rows="3" maxlength="500" required
                class="input mt-1">{{ old('reason') }}</textarea>
            @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        @error('amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

        <div class="flex justify-end gap-2">
            <a href="{{ route('mess.advance-balances.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
@endsection
