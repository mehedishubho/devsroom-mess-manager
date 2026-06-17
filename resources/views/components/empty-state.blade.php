@props(['title', 'description' => null, 'icon' => null, 'actionLabel' => null, 'actionRoute' => null])

<div class="mx-auto max-w-md rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center md:p-12">
    @if ($icon)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto h-10 w-10 text-slate-400" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
        </svg>
    @endif
    <p class="mt-2 text-base font-medium text-slate-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionRoute)
        <a href="{{ $actionRoute }}" class="mt-4 inline-flex min-h-[44px] items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            {{ $actionLabel }}
        </a>
    @endif
</div>
