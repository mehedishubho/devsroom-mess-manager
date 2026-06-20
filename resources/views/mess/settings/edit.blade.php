@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Mess settings') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Update name, address, rent, meal values, and currency.') }}</p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.settings.update') }}" class="flex flex-col gap-4">
            @csrf
            @method('PATCH')

            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium text-slate-900">
                    {{ __('Mess name') }}<span class="text-red-600" aria-hidden="true">*</span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name', $mess->name) }}" required
                    class="input"
                    aria-describedby="name-error">
                @error('name') <p id="name-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="address" class="text-sm font-medium text-slate-900">{{ __('Address') }}</label>
                <textarea name="address" id="address" rows="3" class="input">{{ old('address', $mess->address) }}</textarea>
                @error('address') <p id="address-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="monthly_rent" class="text-sm font-medium text-slate-900">
                        {{ __('Monthly rent (BDT)') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <p class="text-xs text-slate-500">{{ __('Total rent for the mess per month') }}</p>
                    <input type="number" step="0.01" min="0" name="monthly_rent" id="monthly_rent" value="{{ old('monthly_rent', $mess->monthly_rent) }}" required
                        class="input">
                    @error('monthly_rent') <p id="monthly_rent-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="status" class="text-sm font-medium text-slate-900">
                        {{ __('Status') }}<span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <p class="text-xs text-slate-500">{{ __('Inactive messes are hidden from members') }}</p>
                    <select name="status" id="status" required
                        style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23475569%22 stroke-width=%222%22><path d=%22m19.5 8.25-7.5 7.5-7.5-7.5%22/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.25rem;"
                        class="input appearance-none pr-10">
                        <option value="active" @selected(old('status', $mess->status) === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(old('status', $mess->status) === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                    @error('status') <p id="status-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label for="manager_contact" class="text-sm font-medium text-slate-900">{{ __('Manager contact') }}</label>
                <input type="text" name="manager_contact" id="manager_contact" value="{{ old('manager_contact', $mess->manager_contact) }}"
                    class="input">
                @error('manager_contact') <p id="manager_contact-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ __('Save changes') }}
                </button>
                <a href="{{ route('home') }}" class="btn btn-ghost">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
