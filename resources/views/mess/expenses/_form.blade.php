@csrf
@if (isset($expense) && $expense)
    @method('PATCH')
@endif

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="flex flex-col gap-1">
        <label for="date" class="text-sm font-medium text-slate-900">{{ __('Date') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <input type="date" name="date" id="date" value="{{ old('date', isset($expense) ? $expense->date->format('Y-m-d') : now()->toDateString()) }}" required class="input">
        @error('date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="expense_category_id" class="text-sm font-medium text-slate-900">{{ __('Category') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <select name="expense_category_id" id="expense_category_id" required class="input" data-expense-category>
            <option value="" disabled {{ old('expense_category_id', $expense->expense_category_id ?? '') ? '' : 'selected' }}>{{ __('Select a category') }}</option>
            @foreach ($grouped as $kind => $cats)
                <optgroup label="{{ __(ucfirst($kind)) }}">
                    @foreach ($cats as $cat)
                        <option value="{{ $cat->id }}" data-kind="{{ $cat->kind }}" @selected(old('expense_category_id', $expense->expense_category_id ?? '') == $cat->id)>{{ $cat->name }}</option>
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
        <option value="" {{ old('purchased_by', $expense->purchased_by ?? '') ? '' : 'selected' }}>{{ __('— Select member —') }}</option>
        @foreach (\App\Models\Member::where('status', \App\Support\MemberStatus::ACTIVE)->orderBy('name')->get() as $m)
            <option value="{{ $m->id }}" @selected(old('purchased_by', $expense->purchased_by ?? '') == $m->id)>{{ $m->name }}</option>
        @endforeach
    </select>
    @error('purchased_by') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
    <label for="vendor" class="text-sm font-medium text-slate-900">{{ __('Vendor') }}</label>
    <input type="text" name="vendor" id="vendor" value="{{ old('vendor', $expense->vendor ?? '') }}" class="input">
    @error('vendor') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
    <label for="description" class="text-sm font-medium text-slate-900">{{ __('Description') }}</label>
    <textarea name="description" id="description" rows="2" maxlength="500" class="input">{{ old('description', $expense->description ?? '') }}</textarea>
    @error('description') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="flex flex-col gap-1">
        <label for="amount" class="text-sm font-medium text-slate-900">{{ __('Amount') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <input type="number" name="amount" id="amount" value="{{ old('amount', $expense->amount ?? '') }}" required min="0" step="0.01" class="input">
        @error('amount') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="receipt" class="text-sm font-medium text-slate-900">{{ __('Receipt (optional)') }}</label>
        <input type="file" name="receipt" id="receipt" accept="image/*" class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-50">
        @if (isset($expense) && $expense && $expense->receipt_path)
            <p class="text-xs text-slate-500">{{ __('Current receipt:') }}
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($expense->receipt_path) }}" target="_blank" rel="noopener" class="text-emerald-700 hover:underline">{{ __('view') }}</a>
                <span class="text-slate-400">({{ __('upload a new file to replace') }})</span>
            </p>
        @endif
        @error('receipt') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
</div>
