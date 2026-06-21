@props(['request'])

<article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-meal-off-card>
    <header class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-base font-semibold text-slate-900">{{ $request->member?->name ?? '—' }}</h3>
            <p class="text-sm text-slate-600">
                {{ $request->from_date->format('d M Y') }} → {{ $request->to_date->format('d M Y') }}
            </p>
        </div>
        <x-status-pill :variant="$request->status" />
    </header>

    <div class="mt-3" data-meal-off-details>
        <p class="text-sm text-slate-700">
            <span class="font-medium">{{ __('Reason:') }}</span> {{ $request->reason }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
            {{ __('Requested: :when', ['when' => $request->requested_at->diffForHumans()]) }}
        </p>

        @if ($request->status === \App\Support\MealOffStatus::REJECTED && $request->rejection_reason)
            <p class="mt-2 text-sm text-red-700">
                <span class="font-medium">{{ __('Rejection reason:') }}</span> {{ $request->rejection_reason }}
            </p>
        @endif

        @if ($request->status === \App\Support\MealOffStatus::PENDING)
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <form method="POST" action="{{ route('mess.meal-off.approve', $request) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-primary">
                        {{ __('Approve') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('mess.meal-off.reject', $request) }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    @method('PATCH')
                    <input type="text" name="rejection_reason" required minlength="3" maxlength="500"
                        placeholder="{{ __('Reason for rejection (required)') }}"
                        class="input flex-1">
                    <button type="submit" class="btn btn-secondary">
                        {{ __('Reject') }}
                    </button>
                </form>
            </div>
            @error('rejection_reason')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        @endif
    </div>
</article>
