<div class="flex flex-col gap-4 sm:flex-row sm:items-center">
    @if ($member->photo_path)
        <img src="{{ Storage::disk('public')->url($member->photo_path) }}" alt="" class="h-20 w-20 rounded-full object-cover" />
    @else
        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-2xl font-semibold text-emerald-700">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</div>
    @endif
    <div class="flex-1">
        <h2 class="text-lg font-semibold text-slate-900">{{ $member->name }}</h2>
        @if ($member->room_or_seat)
            <p class="text-sm text-slate-600">{{ $member->room_or_seat }}</p>
        @endif
        <p class="mt-1 text-sm text-slate-500">{{ $member->mobile ?? '—' }} · {{ $member->email ?? '—' }}</p>
    </div>
</div>

<form method="POST" action="{{ route('my.profile.update') }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-4">
    @csrf
    @method('PATCH')

    <x-photo-input name="photo" :currentUrl="$member->photo_path ? Storage::disk('public')->url($member->photo_path) : null" :size="80" />

    <div class="flex flex-col gap-1">
        <label for="name" class="text-sm font-medium text-slate-900">{{ __('Name') }}</label>
        <input type="text" name="name" id="name" value="{{ old('name', $member->name) }}"
            class="input">
        @error('name') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="mobile" class="text-sm font-medium text-slate-900">{{ __('Mobile') }}</label>
        <input type="tel" name="mobile" id="mobile" value="{{ old('mobile', $member->mobile) }}"
            class="input">
        @error('mobile') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="email" class="text-sm font-medium text-slate-900">{{ __('Email') }}</label>
        <input type="email" name="email" id="email" value="{{ old('email', $member->email) }}"
            class="input">
        @error('email') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="emergency_contact" class="text-sm font-medium text-slate-900">{{ __('Emergency contact') }}</label>
        <input type="text" name="emergency_contact" id="emergency_contact" value="{{ old('emergency_contact', $member->emergency_contact) }}"
            class="input">
        @error('emergency_contact') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <hr class="border-slate-200">

    <div class="flex flex-col gap-1">
        <label for="current_password" class="text-sm font-medium text-slate-900">{{ __('Current password') }}</label>
        <input type="password" name="current_password" id="current_password"
            class="input @error('current_password') border-red-500 @enderror">
        @error('current_password') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="new_password" class="text-sm font-medium text-slate-900">{{ __('New password') }}</label>
        <input type="password" name="new_password" id="new_password" minlength="8"
            class="input @error('new_password') border-red-500 @enderror">
        @error('new_password') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-1">
        <label for="new_password_confirmation" class="text-sm font-medium text-slate-900">{{ __('Confirm new password') }}</label>
        <input type="password" name="new_password_confirmation" id="new_password_confirmation" minlength="8"
            class="input">
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="submit" class="btn btn-primary">
            {{ __('Save changes') }}
        </button>
    </div>
</form>
