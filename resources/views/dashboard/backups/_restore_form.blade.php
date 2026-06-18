@php
    // D-03 typed-confirm restore form partial. Included by restore.blade.php.
    // Expects $path (the backup zip path) + $expectedMessName (the active
    // mess name to type exactly — research Open Question #3 LOCKED).
@endphp

<form action="{{ route('dashboard.backups.restore.store') }}" method="POST" class="space-y-4 max-w-md">
    @csrf
    <input type="hidden" name="path" value="{{ $path }}" />

    <div class="rounded-md border border-red-200 bg-red-50 p-4">
        <p class="text-sm font-semibold text-red-800">
            {{ __('WARNING: This is a destructive operation.') }}
        </p>
        <p class="mt-1 text-sm text-red-700">
            {{ __('Restoring will overwrite the live database AND all uploaded files (profile photos + receipts). The app will enter maintenance mode until the restore completes.') }}
        </p>
    </div>

    <div>
        <label for="mess_name" class="block text-sm font-medium text-slate-700">
            {{ __('To confirm, type the active mess name exactly:') }}
            <code class="ml-1 rounded bg-slate-100 px-1 py-0.5 text-slate-900">{{ e($expectedMessName) }}</code>
        </label>
        <input
            type="text"
            id="mess_name"
            name="mess_name"
            required
            autocomplete="off"
            class="mt-1 block w-full min-h-[44px] rounded-md border-slate-300 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600 text-base text-slate-900"
        />
        @error('mess_name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('restore')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <button
        type="submit"
        class="inline-flex min-h-[44px] items-center justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
    >
        {{ __('Restore this backup') }}
    </button>
</form>
