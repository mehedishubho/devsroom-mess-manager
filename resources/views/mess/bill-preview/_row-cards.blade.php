@if (empty($members))
    <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
        {{ __('No data for this month.') }}
    </p>
@else
    <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-4 py-3">{{ __('Member') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Meals') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Meal cost') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Fixed share') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Guest meals') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Bill') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Advance applied') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Due') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($members as $row)
                    @include('mess.bill-preview._member-row', ['row' => $row])
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="space-y-3 md:hidden">
        @foreach ($members as $row)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-900">{{ $row['name'] }}</span>
                    <span class="text-xs text-slate-500">{{ $row['active_days'] }} {{ __('days') }}</span>
                </div>
                <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-xs text-slate-500">{{ __('Meals') }}</dt><dd>{{ number_format($row['meals'], 2) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">{{ __('Meal cost') }}</dt><dd>{{ \App\Support\Money::taka($row['meal_cost']) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">{{ __('Fixed share') }}</dt><dd>{{ \App\Support\Money::taka($row['fixed_share']) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">{{ __('Guest meals') }}</dt><dd>{{ \App\Support\Money::taka($row['guest_total']) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">{{ __('Bill') }}</dt><dd class="font-semibold text-slate-900">{{ \App\Support\Money::taka($row['bill']) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">{{ __('Paid') }}</dt><dd class="text-emerald-700">{{ \App\Support\Money::taka($row['bill_payments']) }}</dd></div>
                </dl>
                <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
                    <span class="text-sm font-medium text-slate-700">{{ __('Due') }}</span>
                    <span class="font-semibold {{ $row['due'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ \App\Support\Money::taka($row['due']) }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endif