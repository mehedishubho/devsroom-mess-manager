@csrf

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <label for="name" class="text-sm font-medium text-slate-900">{{ __('Name') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="100"
            class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
        @error('name') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label for="kind" class="text-sm font-medium text-slate-900">{{ __('Kind') }}<span class="text-red-600" aria-hidden="true">*</span></label>
            <select name="kind" id="kind" required
                class="min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                <option value="bazar" @selected(old('kind') === 'bazar')>{{ __('Bazar') }}</option>
                <option value="fixed" @selected(old('kind') === 'fixed')>{{ __('Fixed') }}</option>
                <option value="other" @selected(old('kind') === 'other')>{{ __('Other') }}</option>
            </select>
            @error('kind') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="sort_order" class="text-sm font-medium text-slate-900">{{ __('Sort order') }}</label>
            <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                class="min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
    </div>
</div>

<div class="mt-4 flex flex-wrap items-center gap-2">
    <button type="submit" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
        {{ __('Add category') }}
    </button>
</div>
