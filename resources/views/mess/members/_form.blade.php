@csrf
@if ($method !== 'POST')
    @method($method)
@endif

<div class="flex flex-col gap-4">
    <x-photo-input
        name="photo"
        :currentUrl="$member->photo_path ? Storage::disk('public')->url($member->photo_path) : null"
        :size="96"
    />

    <div class="flex flex-col gap-1">
        <label for="name" class="text-sm font-medium text-slate-900">
            {{ __('Name') }}<span class="text-red-600" aria-hidden="true">*</span>
        </label>
        <input type="text" name="name" id="name" value="{{ old('name', $member->name) }}" required
            class="input"
            aria-describedby="name-error">
        @error('name') <p id="name-error" class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label for="mobile" class="text-sm font-medium text-slate-900">{{ __('Mobile') }}</label>
            <input type="tel" name="mobile" id="mobile" value="{{ old('mobile', $member->mobile) }}"
                class="input"
                aria-describedby="mobile-help">
            <p id="mobile-help" class="text-xs text-slate-500">{{ __('BD format, e.g. 01700000000') }}</p>
            @error('mobile') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="email" class="text-sm font-medium text-slate-900">{{ __('Email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email', $member->email) }}"
                class="input">
            @error('email') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label for="nid" class="text-sm font-medium text-slate-900">{{ __('NID') }}</label>
            <input type="text" name="nid" id="nid" value="{{ old('nid', $member->nid) }}"
                class="input">
            @error('nid') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="profession" class="text-sm font-medium text-slate-900">{{ __('Profession') }}</label>
            <input type="text" name="profession" id="profession" value="{{ old('profession', $member->profession) }}"
                class="input">
            @error('profession') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-col gap-1">
        <label for="room_or_seat" class="text-sm font-medium text-slate-900">{{ __('Room or seat') }}</label>
        <input type="text" name="room_or_seat" id="room_or_seat" value="{{ old('room_or_seat', $member->room_or_seat) }}"
            class="input"
            aria-describedby="room-help">
        <p id="room-help" class="text-xs text-slate-500">{{ __('e.g. R-201 or 3rd floor, R-12') }}</p>
        @error('room_or_seat') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label for="joining_date" class="text-sm font-medium text-slate-900">{{ __('Joining date') }}</label>
            <input type="date" name="joining_date" id="joining_date" value="{{ old('joining_date', optional($member->joining_date)->format('Y-m-d')) }}"
                class="input">
            @error('joining_date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="status" class="text-sm font-medium text-slate-900">
                {{ __('Status') }}<span class="text-red-600" aria-hidden="true">*</span>
            </label>
            <select name="status" id="status" required data-show-leaving-date
                style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23475569%22 stroke-width=%222%22><path d=%22m19.5 8.25-7.5 7.5-7.5-7.5%22/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.25rem;"
                class="input appearance-none pr-10">
                <option value="active" @selected(old('status', $member->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(old('status', $member->status) === 'inactive')>{{ __('Inactive') }}</option>
                <option value="former" @selected(old('status', $member->status) === 'former')>{{ __('Former') }}</option>
            </select>
            @error('status') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-col gap-1" data-leaving-date-field @if (old('status', $member->status) !== 'former') style="display:none" @endif>
        <label for="leaving_date" class="text-sm font-medium text-slate-900">{{ __('Leaving date') }}</label>
        <input type="date" name="leaving_date" id="leaving_date" value="{{ old('leaving_date', optional($member->leaving_date)->format('Y-m-d')) }}"
            class="input">
        @error('leaving_date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="emergency_contact" class="text-sm font-medium text-slate-900">{{ __('Emergency contact') }}</label>
        <input type="text" name="emergency_contact" id="emergency_contact" value="{{ old('emergency_contact', $member->emergency_contact) }}"
            class="input"
            aria-describedby="emergency-help">
        <p id="emergency-help" class="text-xs text-slate-500">{{ __('Name and phone of a relative') }}</p>
        @error('emergency_contact') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-wrap items-center gap-2">
    <button type="submit" class="btn btn-primary">
        {{ __('Save member') }}
    </button>
    <a href="{{ route('mess.members.index') }}" class="btn btn-ghost">
        {{ __('Cancel') }}
    </a>
</div>

@once
    <script>
        (function () {
            const sel = document.querySelector('[data-show-leaving-date]');
            const field = document.querySelector('[data-leaving-date-field]');
            if (!sel || !field) return;
            sel.addEventListener('change', function () {
                if (sel.value === 'former') { field.style.display = ''; }
                else { field.style.display = 'none'; }
            });
        })();
    </script>
@endonce
