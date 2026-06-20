<div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50" @keydown.escape.window="open = false">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.outside="open = false">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Confirm close') }}</h2>
        <p class="mt-2 text-sm text-slate-700">{{ __('This will lock :label and write immutable summaries. Corrections will still be possible afterwards.', ['label' => \Carbon\Carbon::create($year, $month, 1)->format('F Y')]) }}</p>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="open = false" class="btn btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" class="btn btn-danger">{{ __('Yes, close now') }}</button>
        </div>
    </div>
</div>
