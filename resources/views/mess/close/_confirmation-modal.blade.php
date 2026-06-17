<div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50" @keydown.escape.window="open = false">
    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg" @click.outside="open = false">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Confirm close') }}</h2>
        <p class="mt-2 text-sm text-slate-700">{{ __('This will lock :label and write immutable summaries. Corrections will still be possible afterwards.', ['label' => \Carbon\Carbon::create($year, $month, 1)->format('F Y')]) }}</p>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="open = false" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Cancel') }}</button>
            <button type="submit" class="inline-flex items-center rounded-md bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700">{{ __('Yes, close now') }}</button>
        </div>
    </div>
</div>
