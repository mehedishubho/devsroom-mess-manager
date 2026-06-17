@props(['member', 'showStatus' => true, 'size' => 'md'])

@php
    $avatarSize = $size === 'sm' ? 'h-10 w-10' : 'h-12 w-12';
    $initials = strtoupper(mb_substr($member->name, 0, 1));
@endphp

<a href="{{ route('mess.members.show', $member) }}" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:bg-slate-50 min-h-[44px]">
    @if ($member->photo_path)
        <img src="{{ Storage::disk('public')->url($member->photo_path) }}" alt="{{ $member->name }}" class="{{ $avatarSize }} rounded-full object-cover" />
    @else
        <div class="{{ $avatarSize }} flex items-center justify-center rounded-full bg-emerald-100 text-base font-semibold text-emerald-700">
            {{ $initials }}
        </div>
    @endif
    <div class="flex-1 min-w-0">
        <p class="truncate text-sm font-semibold text-slate-900">{{ $member->name }}</p>
        @if ($member->room_or_seat)
            <p class="truncate text-xs text-slate-500">{{ $member->room_or_seat }}</p>
        @endif
    </div>
    @if ($showStatus)
        <x-status-pill :variant="$member->status" />
    @endif
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 flex-shrink-0 text-slate-400" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
    </svg>
</a>
