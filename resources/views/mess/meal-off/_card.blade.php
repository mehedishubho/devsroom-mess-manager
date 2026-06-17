@props(['request'])

<article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" data-meal-off-card>
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
                    <button type="submit" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        {{ __('Approve') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('mess.meal-off.reject', $request) }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    @method('PATCH')
                    <input type="text" name="rejection_reason" required minlength="3" maxlength="500"
                        placeholder="{{ __('Reason for rejection (required)') }}"
                        class="min-h-[44px] flex-1 rounded-md border border-slate-300 px-3 py-2 text-base text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    <button type="submit" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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
