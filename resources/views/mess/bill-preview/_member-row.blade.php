<tr class="hover:bg-slate-50">
    <td class="px-4 py-3 font-medium text-slate-900">{{ $row['name'] }}</td>
    <td class="px-4 py-3 text-right text-slate-700">{{ number_format($row['meals'], 2) }}</td>
    <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::taka($row['meal_cost']) }}</td>
    <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::taka($row['fixed_share']) }}</td>
    <td class="px-4 py-3 text-right text-slate-700">{{ \App\Support\Money::taka($row['guest_total']) }}</td>
    <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ \App\Support\Money::taka($row['bill']) }}</td>
    <td class="px-4 py-3 text-right text-emerald-700">{{ \App\Support\Money::taka($row['bill_payments']) }}</td>
    <td class="px-4 py-3 text-right text-emerald-700">{{ \App\Support\Money::taka($row['advance_applied']) }}</td>
    <td class="px-4 py-3 text-right font-semibold {{ $row['due'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ \App\Support\Money::taka($row['due']) }}</td>
</tr>