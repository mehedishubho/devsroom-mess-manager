@csrf
@if (isset($method) && $method !== 'POST')
    @method($method)
@endif

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <label for="member_id" class="text-sm font-medium text-slate-900">{{ __('Host member') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <select name="member_id" id="member_id" required
            class="input">
            @foreach (\App\Models\Member::where('status', \App\Support\MemberStatus::ACTIVE)->orderBy('name')->get() as $m)
                <option value="{{ $m->id }}" @selected(old('member_id', $guestMeal->member_id) == $m->id)>{{ $m->name }}{{ $m->room_or_seat ? ' — '.$m->room_or_seat : '' }}</option>
            @endforeach
        </select>
        @error('member_id') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="guest_name" class="text-sm font-medium text-slate-900">{{ __('Guest name') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <input type="text" name="guest_name" id="guest_name" value="{{ old('guest_name', $guestMeal->guest_name) }}" required
            class="input">
        @error('guest_name') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label for="date" class="text-sm font-medium text-slate-900">{{ __('Date') }}<span class="text-red-600" aria-hidden="true">*</span></label>
            <input type="date" name="date" id="date" value="{{ old('date', optional($guestMeal->date)->format('Y-m-d') ?? now()->toDateString()) }}" required
                class="input">
            @error('date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="meal_type" class="text-sm font-medium text-slate-900">{{ __('Meal type') }}<span class="text-red-600" aria-hidden="true">*</span></label>
            <select name="meal_type" id="meal_type" required
                class="input">
                <option value="breakfast" @selected(old('meal_type', $guestMeal->meal_type) === 'breakfast')>{{ __('Breakfast') }} (0.5)</option>
                <option value="lunch" @selected(old('meal_type', $guestMeal->meal_type) === 'lunch')>{{ __('Lunch') }} (1.0)</option>
                <option value="dinner" @selected(old('meal_type', $guestMeal->meal_type) === 'dinner')>{{ __('Dinner') }} (1.0)</option>
            </select>
            @error('meal_type') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-col gap-1">
        <label for="quantity" class="text-sm font-medium text-slate-900">{{ __('Quantity') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <input type="number" name="quantity" id="quantity" value="{{ old('quantity', $guestMeal->quantity ?? 1) }}" required min="1" step="1"
            class="input">
        @error('quantity') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-wrap items-center gap-2">
    <button type="submit" class="btn btn-primary">
        {{ __('Save guest meal') }}
    </button>
    <a href="{{ route('mess.guest-meals.index') }}" class="btn btn-ghost">
        {{ __('Cancel') }}
    </a>
</div>
