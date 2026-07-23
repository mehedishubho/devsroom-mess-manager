@if ($members->isEmpty())
    <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
        {{ __('No active members yet.') }}
    </p>
@else
    <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-4 py-3">{{ __('Member') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Last updated') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($members as $member)
                    @php
                        $bal = $member->advanceBalance;
                        $net = $bal?->netBalance() ?? 0;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($net > 0)
                                <span class="font-medium text-emerald-700">{{ __('Credit') }} {{ \App\Support\Money::taka($net) }}</span>
                            @elseif ($net < 0)
                                <span class="font-medium text-rose-700">{{ __('Owes') }} {{ \App\Support\Money::taka(abs($net)) }}</span>
                            @else
                                <span class="text-slate-400">{{ __('Settled') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-500">{{ $bal?->last_updated_at?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('mess.members.wallet', $member) }}" class="text-emerald-700 hover:underline">{{ __('Wallet') }}</a>
                            <span class="mx-1 text-slate-300">·</span>
                            <a href="{{ route('mess.advance-balances.adjust', $member) }}" class="text-emerald-700 hover:underline">{{ __('Adjust') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="space-y-3 md:hidden">
        @foreach ($members as $member)
            @php
                $bal = $member->advanceBalance;
                $net = $bal?->netBalance() ?? 0;
            @endphp
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $member->name }}</span>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('mess.members.wallet', $member) }}" class="text-sm text-emerald-700 hover:underline">{{ __('Wallet') }}</a>
                        <a href="{{ route('mess.advance-balances.adjust', $member) }}" class="text-sm text-emerald-700 hover:underline">{{ __('Adjust') }}</a>
                    </div>
                </div>
                <div class="mt-2 text-sm">
                    @if ($net > 0)
                        <div class="font-semibold text-emerald-700">{{ __('Credit') }} {{ \App\Support\Money::taka($net) }}</div>
                    @elseif ($net < 0)
                        <div class="font-semibold text-rose-700">{{ __('Owes') }} {{ \App\Support\Money::taka(abs($net)) }}</div>
                    @else
                        <div class="font-semibold text-slate-500">{{ __('Settled') }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
