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
        <label for="emergency_contact" class="text-sm font-medium text-slate-900">{{ __('Emergency contact') }}</label>
        <input type="text" name="emergency_contact" id="emergency_contact" value="{{ old('emergency_contact', $member->emergency_contact) }}"
            class="input">
        @error('emergency_contact') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="submit" class="btn btn-primary">
            {{ __('Save changes') }}
        </button>
    </div>
</form>

<div class="mt-6 border-t border-slate-200 pt-4">
    <a href="#" class="btn btn-secondary">
        {{ __('Change password') }}
    </a>
    <p class="mt-1 text-xs text-slate-500">{{ __('Use the password reset link sent to your email, or contact the manager.') }}</p>
</div>
