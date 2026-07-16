@php
    // Task 3 (quick-260717-2q3) — Cancel button fix.
    // Root cause: previously, @click.outside="open = false" was attached to
    // the INNER white panel. Under Alpine v3 timing, the same click that
    // opened the modal can bubble to the document AFTER the listener attaches
    // — the panel sees the trigger click as "outside" and immediately closes
    // itself. Additionally, the Cancel button's @click="open = false" works,
    // but its click also bubbles to the panel which had @click.outside
    // attached, leaving Alpine's x-show transition state stale (re-appearing
    // on next trigger click).
    //
    // Fix: move @click.outside onto the OUTER backdrop (the dark overlay).
    // Clicking the dark area now closes — desired UX. The Cancel button's
    // existing @click="open = false" works without interference. The inner
    // panel keeps NO outside-click handler.
    @endphp
<div x-cloak x-show="open"
     class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50"
     @click.outside="open = false"
     @keydown.escape.window="open = false">
    {{-- The inner panel stops propagation so clicks inside it do NOT trigger
         the backdrop's @click.outside — Alpine handles this automatically
         via the .outside modifier. --}}
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Confirm close') }}</h2>
        <p class="mt-2 text-sm text-slate-700">{{ __('This will lock :label and write immutable summaries. Corrections will still be possible afterwards.', ['label' => \Carbon\Carbon::create($year, $month, 1)->format('F Y')]) }}</p>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="open = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" class="btn btn-danger">{{ __('Yes, close now') }}</button>
        </div>
    </div>
</div>
