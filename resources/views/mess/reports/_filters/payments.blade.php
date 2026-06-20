@php
    use App\Support\PaymentMethod;

    $memberId = request()->query('member_id');
    $method = request()->query('method');
    $from = request()->query('from');
    $to = request()->query('to');
    $hasFilters = filled($memberId) || filled($method) || filled($from) || filled($to);
@endphp

<section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-700">{{ __('Filters') }}</h2>
        @if ($hasFilters)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 12h12M10 19.5h4"/></svg>
                {{ __('Filtered') }}
            </span>
        @endif
    </div>

    <form method="GET" action="{{ route('mess.reports.payments') }}" class="flex flex-wrap items-end gap-3">
        <label class="block text-xs font-medium text-slate-600">
            {{ __('Member') }}
            <select name="member_id" class="input mt-1 w-auto min-w-40">
                <option value="">{{ __('All') }}</option>
                @foreach ($members as $m)
                    <option value="{{ $m->id }}" @selected((string) $memberId === (string) $m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs font-medium text-slate-600">
            {{ __('Method') }}
            <select name="method" class="input mt-1 w-auto min-w-36">
                <option value="">{{ __('All') }}</option>
                @foreach (PaymentMethod::ALL as $m)
                    <option value="{{ $m }}" @selected($method === $m)>{{ __(PaymentMethod::LABELS[$m] ?? ucfirst($m)) }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs font-medium text-slate-600">
            {{ __('From') }}
            <input type="date" name="from" value="{{ $from }}" class="input input-date mt-1 w-auto max-w-44" />
        </label>
        <label class="block text-xs font-medium text-slate-600">
            {{ __('To') }}
            <input type="date" name="to" value="{{ $to }}" class="input input-date mt-1 w-auto max-w-44" />
        </label>
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-dark">{{ __('Apply') }}</button>
            <a href="{{ route('mess.reports.payments', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]) }}" class="btn btn-secondary">{{ __('This month') }}</a>
            <a href="{{ route('mess.reports.payments', ['from' => now()->subMonth()->startOfMonth()->toDateString(), 'to' => now()->subMonth()->endOfMonth()->toDateString()]) }}" class="btn btn-secondary">{{ __('Last month') }}</a>
            @if ($hasFilters)
                <a href="{{ route('mess.reports.payments') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
            @endif
        </div>
    </form>
</section>
