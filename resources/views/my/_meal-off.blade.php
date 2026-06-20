<h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Request meal off') }}</h2>
<form method="POST" action="{{ route('my.meal-off.store') }}" class="mt-3 flex flex-col gap-4">
    @csrf
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label for="from_date" class="text-sm font-medium text-slate-900">{{ __('From date') }}<span class="text-red-600" aria-hidden="true">*</span></label>
            <input type="date" name="from_date" id="from_date" value="{{ old('from_date') }}" required min="{{ now()->toDateString() }}"
                class="input">
            @error('from_date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-col gap-1">
            <label for="to_date" class="text-sm font-medium text-slate-900">{{ __('To date') }}<span class="text-red-600" aria-hidden="true">*</span></label>
            <input type="date" name="to_date" id="to_date" value="{{ old('to_date') }}" required min="{{ now()->toDateString() }}"
                class="input">
            @error('to_date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="flex flex-col gap-1">
        <label for="reason" class="text-sm font-medium text-slate-900">{{ __('Reason') }}<span class="text-red-600" aria-hidden="true">*</span></label>
        <textarea name="reason" id="reason" rows="3" required minlength="3" maxlength="500"
            placeholder="{{ __('e.g. Going home, official tour, family event') }}"
            class="input">{{ old('reason') }}</textarea>
        @error('reason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
    <div>
        <button type="submit" class="btn btn-primary">
            {{ __('Request meal off') }}
        </button>
    </div>
</form>

<h2 class="mt-8 text-lg font-semibold leading-tight text-slate-900">{{ __('Your meal off requests') }}</h2>
@if ($mealOffRequests->isEmpty())
    <p class="mt-2 text-sm text-slate-600">{{ __('You have no meal off requests.') }}</p>
@else
    <ul class="mt-3 divide-y divide-slate-200">
        @foreach ($mealOffRequests as $req)
            <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-900">
                        {{ $req->from_date->format('d M Y') }} → {{ $req->to_date->format('d M Y') }}
                    </p>
                    <p class="text-xs text-slate-500">{{ __('Reason: :reason', ['reason' => $req->reason]) }}</p>
                    @if ($req->status === \App\Support\MealOffStatus::REJECTED && !empty($req->rejection_reason))
                        <p class="text-xs text-red-700">{{ __('Rejected: :reason', ['reason' => $req->rejection_reason]) }}</p>
                    @endif
                </div>
                <x-status-pill :variant="$req->status" />
            </li>
        @endforeach
    </ul>
@endif
