@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Add correction for :label', ['label' => \Carbon\Carbon::create($closing->year, $closing->month, 1)->format('F Y')]) }}</h1>
    </header>
    <form method="POST" action="{{ route('mess.closings.corrections.store', $closing) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        @csrf
        <div>
            <label for="member_id" class="block text-sm font-medium text-slate-700">{{ __('Member') }}</label>
            <select name="member_id" id="member_id" class="mt-1 input text-sm@error('member_id') input-error @enderror">
                @foreach ($members as $id => $name)
                    <option value="{{ $id }}" @selected(old('member_id') == $id)>{{ $name }}</option>
                @endforeach
            </select>
            @error('member_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="amount" class="block text-sm font-medium text-slate-700">{{ __('Amount') }} <span class="text-xs text-slate-500">{{ __('(positive = credit advance; negative = add to due)') }}</span></label>
            <input type="number" name="amount" id="amount" step="0.01" value="{{ old('amount') }}" class="mt-1 input text-sm@error('amount') input-error @enderror" />
            @error('amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="reason" class="block text-sm font-medium text-slate-700">{{ __('Reason') }}</label>
            <textarea name="reason" id="reason" rows="3" class="mt-1 input text-sm@error('reason') input-error @enderror">{{ old('reason') }}</textarea>
            @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="applied_to_year" class="block text-sm font-medium text-slate-700">{{ __('Applied to year') }}</label>
                <input type="number" name="applied_to_year" id="applied_to_year" value="{{ old('applied_to_year', $closing->year) }}" min="2020" max="2100" class="mt-1 input text-sm@error('applied_to_year') input-error @enderror" />
                @error('applied_to_year') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="applied_to_month" class="block text-sm font-medium text-slate-700">{{ __('Applied to month') }}</label>
                <input type="number" name="applied_to_month" id="applied_to_month" value="{{ old('applied_to_month', $closing->month) }}" min="1" max="12" class="mt-1 input text-sm@error('applied_to_month') input-error @enderror" />
                @error('applied_to_month') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('mess.closings.show', $closing) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
@endsection
