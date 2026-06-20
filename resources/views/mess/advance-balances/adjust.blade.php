@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Adjust balance') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $member->name }} — {{ __('current advance') }}: <span class="font-semibold text-emerald-700">{{ \App\Support\Money::taka($member->advanceBalance?->balance ?? 0) }}</span>, {{ __('current due') }}: <span class="font-semibold text-rose-700">{{ \App\Support\Money::taka($member->advanceBalance?->due_balance ?? 0) }}</span></p>
    </header>
    <form method="POST" action="{{ route('mess.advance-balances.storeAdjust', $member) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        @csrf
        <div>
            <label for="amount" class="block text-sm font-medium text-slate-700">{{ __('Amount (BDT)') }} <span class="text-xs text-slate-500">{{ __('(positive = credit advance, negative = add to due)') }}</span></label>
            <input type="number" name="amount" id="amount" step="0.01" value="{{ old('amount') }}" class="input mt-1 @error('amount') input-error @enderror" />
            @error('amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="reason" class="block text-sm font-medium text-slate-700">{{ __('Reason') }}</label>
            <textarea name="reason" id="reason" rows="3" maxlength="500" class="input mt-1 @error('reason') input-error @enderror">{{ old('reason') }}</textarea>
            @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('mess.advance-balances.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
@endsection