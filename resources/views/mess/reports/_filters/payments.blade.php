@php
    use App\Support\PaymentMethod;

    $memberId = request()->query('member_id');
    $method = request()->query('method');
    $from = request()->query('from');
    $to = request()->query('to');
@endphp

<form method="GET" action="{{ route('mess.reports.payments') }}" class="mb-4 flex flex-wrap items-end gap-2">
    <label class="text-xs font-medium text-slate-600">
        {{ __('Member') }}
        <select name="member_id" class="mt-1 block min-h-[44px] rounded-md border-slate-300 text-base">
            <option value="">{{ __('All') }}</option>
            @foreach ($members as $m)
                <option value="{{ $m->id }}" @selected((string) $memberId === (string) $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-medium text-slate-600">
        {{ __('Method') }}
        <select name="method" class="mt-1 block min-h-[44px] rounded-md border-slate-300 text-base">
            <option value="">{{ __('All') }}</option>
            @foreach (PaymentMethod::ALL as $m)
                <option value="{{ $m }}" @selected($method === $m)>{{ __(PaymentMethod::LABELS[$m] ?? ucfirst($m)) }}</option>
            @endforeach
        </select>
    </label>
    <label class="text-xs font-medium text-slate-600">
        {{ __('From') }}
        <input type="date" name="from" value="{{ $from }}" class="mt-1 block min-h-[44px] rounded-md border-slate-300 text-base" />
    </label>
    <label class="text-xs font-medium text-slate-600">
        {{ __('To') }}
        <input type="date" name="to" value="{{ $to }}" class="mt-1 block min-h-[44px] rounded-md border-slate-300 text-base" />
    </label>
    <button type="submit" class="min-h-[44px] inline-flex items-center justify-center rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">
        {{ __('Apply') }}
    </button>
    <a href="{{ route('mess.reports.payments', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]) }}"
       class="min-h-[44px] inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
        {{ __('This month') }}
    </a>
    <a href="{{ route('mess.reports.payments', ['from' => now()->subMonth()->startOfMonth()->toDateString(), 'to' => now()->subMonth()->endOfMonth()->toDateString()]) }}"
       class="min-h-[44px] inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
        {{ __('Last month') }}
    </a>
</form>
