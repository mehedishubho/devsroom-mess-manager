@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Create your mess') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('This is the only mess this installation will manage. You can edit it later from the dashboard.') }}</p>
    </header>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('onboarding.store') }}" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium text-slate-900">
                    {{ __('Mess name') }}<span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                    class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                @error('name') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="address" class="text-sm font-medium text-slate-900">{{ __('Address') }}</label>
                <textarea name="address" id="address" rows="3" class="min-h-[88px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">{{ old('address') }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="monthly_rent" class="text-sm font-medium text-slate-900">
                        {{ __('Monthly rent (BDT)') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <p class="text-xs text-slate-500">{{ __('0 for no rent') }}</p>
                    <input type="number" step="0.01" min="0" name="monthly_rent" id="monthly_rent" value="{{ old('monthly_rent', 0) }}" required
                        class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    @error('monthly_rent') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="manager_contact" class="text-sm font-medium text-slate-900">{{ __('Your contact info') }}</label>
                    <input type="text" name="manager_contact" id="manager_contact" value="{{ old('manager_contact') }}" placeholder="Phone or email"
                        class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>
            </div>

            <fieldset class="flex flex-col gap-4 rounded-md border border-slate-200 p-4">
                <legend class="px-2 text-sm font-semibold text-slate-900">{{ __('Meal values') }}</legend>
                <p class="text-xs text-slate-500">{{ __('Default 0.5 / 1 / 1. Most messes use this.') }}</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="flex flex-col gap-1">
                        <label for="meal_breakfast" class="text-sm font-medium text-slate-900">{{ __('Breakfast') }}</label>
                        <input type="number" step="0.1" min="0" name="meal_breakfast" id="meal_breakfast" value="{{ old('meal_breakfast', 0.5) }}" required
                            class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="meal_lunch" class="text-sm font-medium text-slate-900">{{ __('Lunch') }}</label>
                        <input type="number" step="0.1" min="0" name="meal_lunch" id="meal_lunch" value="{{ old('meal_lunch', 1) }}" required
                            class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="meal_dinner" class="text-sm font-medium text-slate-900">{{ __('Dinner') }}</label>
                        <input type="number" step="0.1" min="0" name="meal_dinner" id="meal_dinner" value="{{ old('meal_dinner', 1) }}" required
                            class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    </div>
                </div>
            </fieldset>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="currency" class="text-sm font-medium text-slate-900">
                        {{ __('Currency code') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input type="text" name="currency" id="currency" value="{{ old('currency', 'BDT') }}" maxlength="3" required
                        class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <div class="flex flex-col gap-1">
                    <label for="date_format" class="text-sm font-medium text-slate-900">
                        {{ __('Date format') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <select name="date_format" id="date_format" required
                        style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23475569%22 stroke-width=%222%22><path d=%22m19.5 8.25-7.5 7.5-7.5-7.5%22/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.25rem;"
                        class="min-h-[44px] w-full appearance-none rounded-md border border-slate-300 bg-white px-3 py-2 pr-10 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="DD-MM-YYYY" @selected(old('date_format', 'DD-MM-YYYY') === 'DD-MM-YYYY')>DD-MM-YYYY</option>
                        <option value="MM-DD-YYYY" @selected(old('date_format') === 'MM-DD-YYYY')>MM-DD-YYYY</option>
                        <option value="YYYY-MM-DD" @selected(old('date_format') === 'YYYY-MM-DD')>YYYY-MM-DD</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 min-h-[44px]">
                    {{ __('Create mess') }}
                </button>
            </div>
        </form>
    </div>
@endsection
