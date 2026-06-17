@props(['name', 'currentPath' => null, 'currentUrl' => null, 'size' => 96, 'capture' => true])

<div class="flex flex-col items-start gap-2">
    <div
        class="overflow-hidden rounded-full border border-slate-200 bg-slate-100"
        style="width: {{ $size }}px; height: {{ $size }}px;"
    >
        @if ($currentUrl)
            <img src="{{ $currentUrl }}" alt="{{ __('Current photo') }}" class="h-full w-full object-cover" />
        @else
            <div class="flex h-full w-full items-center justify-center text-slate-400">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-1/2 w-1/2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            </div>
        @endif
    </div>
    <input
        type="file"
        name="{{ $name }}"
        id="{{ $name }}"
        accept="image/*"
        @if ($capture) capture="environment" @endif
        aria-label="{{ $capture ? __('Take or choose a photo') : __('Choose a photo') }}"
        class="block w-full max-w-xs text-sm text-slate-700 file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-900 hover:file:bg-slate-50"
    />
    @error($name) <p class="text-sm text-red-700">{{ $message }}</p> @enderror
</div>
