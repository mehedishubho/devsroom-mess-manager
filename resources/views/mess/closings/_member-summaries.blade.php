@if ($closing->memberSummaries->isEmpty())
    <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
        {{ __('No member summaries.') }}
    </p>
@else
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-4 py-3">{{ __('Member') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Meals') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Meal cost') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Fixed') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Guest') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Gross') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Advance') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Net') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($closing->memberSummaries as $s)
                    <tr>
                        <td class="px-4 py-3 text-slate-900">{{ $s->member?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ number_format((float) $s->total_meals, 1) }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::taka($s->meal_cost) }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::taka($s->fixed_cost_share) }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::taka($s->guest_meal_charge) }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::taka($s->gross_bill) }}</td>
                        <td class="px-4 py-3 text-right text-emerald-700">{{ \App\Support\Money::taka($s->advance_applied) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ \App\Support\Money::taka($s->net_bill) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
