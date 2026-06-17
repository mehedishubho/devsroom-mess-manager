@props(['unreadCount' => 0])
<a href="{{ route('notifications.index') }}" class="relative inline-flex items-center rounded-md p-2 text-slate-700 hover:bg-slate-100" aria-label="{{ __('Notifications') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
    </svg>
    @if ($unreadCount > 0)
        <span class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold leading-none text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    @endif
</a>
