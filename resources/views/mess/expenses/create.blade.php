@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Add expense') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Record a bazar, fixed, or other expense. The category determines the kind.') }}</p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.expenses.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="date" class="text-sm font-medium text-slate-900">{{ __('Date') }}<span class="text-red-600" aria-hidden="true">*</span></label>
                    <input type="date" name="date" id="date" value="{{ old('date', now()->toDateString()) }}" required class="input">
                    @error('date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="expense_category_id" class="text-sm font-medium text-slate-900">{{ __('Category') }}<span class="text-red-600" aria-hidden="true">*</span></label>
                    <select name="expense_category_id" id="expense_category_id" required class="input" data-expense-category>
                        <option value="" disabled {{ old('expense_category_id') ? '' : 'selected' }}>{{ __('Select a category') }}</option>
                        @foreach ($grouped as $kind => $cats)
                            <optgroup label="{{ __(ucfirst($kind)) }}">
                                @foreach ($cats as $cat)
                                    <option value="{{ $cat->id }}" data-kind="{{ $cat->kind }}" @selected(old('expense_category_id') == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('expense_category_id') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Purchased by — required for bazar-kind categories only. Alpine reads the
                 selected <option>'s data-kind attribute and toggles the required property. --}}
            <div class="flex flex-col gap-1" x-data="{ kindRequired: false }" x-init="const sel = document.getElementById('expense_category_id'); const sync = () => { const opt = sel.options[sel.selectedIndex]; kindRequired = opt ? opt.dataset.kind === '{{ \App\Support\ExpenseKind::BAZAR }}' : false; }; sel.addEventListener('change', sync); sync();">
                <label for="purchased_by" class="text-sm font-medium text-slate-900">
                    {{ __('Purchased by') }}
                    <span class="text-red-600" aria-hidden="true" x-show="kindRequired">*</span>
                    <span class="text-xs font-normal text-slate-500" x-show="kindRequired" x-cloak>{{ __('(required for bazar)') }}</span>
                </label>
                <select name="purchased_by" id="purchased_by" class="input" :required="kindRequired">
                    <option value="" {{ old('purchased_by') ? '' : 'selected' }}>{{ __('— Select member —') }}</option>
                    @foreach (\App\Models\Member::where('status', \App\Support\MemberStatus::ACTIVE)->orderBy('name')->get() as $m)
                        <option value="{{ $m->id }}" @selected(old('purchased_by') == $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
                @error('purchased_by') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="vendor" class="text-sm font-medium text-slate-900">{{ __('Vendor') }}</label>
                <input type="text" name="vendor" id="vendor" value="{{ old('vendor') }}" class="input">
                @error('vendor') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="description" class="text-sm font-medium text-slate-900">{{ __('Description') }}</label>
                <textarea name="description" id="description" rows="2" maxlength="500" class="input">{{ old('description') }}</textarea>
                @error('description') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="amount" class="text-sm font-medium text-slate-900">{{ __('Amount') }}<span class="text-red-600" aria-hidden="true">*</span></label>
                    <input type="number" name="amount" id="amount" value="{{ old('amount') }}" required min="0" step="0.01" class="input">
                    @error('amount') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="receipt" class="text-sm font-medium text-slate-900">{{ __('Receipt (optional)') }}</label>
                    <input type="file" name="receipt" id="receipt" accept="image/*" class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-50">
                    @error('receipt') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ __('Save expense') }}
                </button>
                <a href="{{ route('mess.expenses.index') }}" class="btn btn-ghost">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
