@php
    // D-04 restore_tests health badge partial. $latestRestoreTest is a
    // RestoreTest model OR null (no restore-test has ever run).
    $status = $latestRestoreTest?->status;
    $badgeClass = match ($status) {
        'passed' => 'bg-emerald-100 text-emerald-800',
        'failed' => 'bg-red-100 text-red-800',
        'running' => 'bg-blue-100 text-blue-800',
        'error' => 'bg-red-100 text-red-800',
        default => 'bg-slate-100 text-slate-600',
    };
    $label = $status
        ? __('Restore-test: :status', ['status' => ucfirst($status)])
        : __('No restore-test yet');
@endphp

<span class="inline-flex min-h-[44px] items-center rounded-full px-3 py-1 text-sm font-medium {{ $badgeClass }}">
    {{ $label }}
    @if ($latestRestoreTest?->ran_at)
        <span class="ml-2 text-xs text-slate-500">{{ $latestRestoreTest->ran_at->diffForHumans() }}</span>
    @endif
</span>

@if ($latestRestoreTest && $status !== 'passed' && $latestRestoreTest->message)
    <p class="mt-2 text-sm text-slate-600">{{ $latestRestoreTest->message }}</p>
@endif
