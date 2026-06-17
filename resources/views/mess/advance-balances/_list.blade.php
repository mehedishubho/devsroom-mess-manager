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
                    <th class="px-4 py-3 text-right">{{ __('Advance (credit)') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Due (debt)') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Last updated') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($members as $member)
                    @php
                        $bal = $member->advanceBalance;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-right text-emerald-700">{{ \App\Support\Money::taka($bal?->balance ?? 0) }}</td>
                        <td class="px-4 py-3 text-right text-rose-700">{{ \App\Support\Money::taka($bal?->due_balance ?? 0) }}</td>
                        <td class="px-4 py-3 text-right text-slate-500">{{ $bal?->last_updated_at?->format('d-m-Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
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
            @endphp
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $member->name }}</span>
                    <a href="{{ route('mess.advance-balances.adjust', $member) }}" class="text-sm text-emerald-700 hover:underline">{{ __('Adjust') }}</a>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="text-xs text-slate-500">{{ __('Advance') }}</div>
                        <div class="font-semibold text-emerald-700">{{ \App\Support\Money::taka($bal?->balance ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">{{ __('Due') }}</div>
                        <div class="font-semibold text-rose-700">{{ \App\Support\Money::taka($bal?->due_balance ?? 0) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif