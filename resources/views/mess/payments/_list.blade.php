@if ($payments->isEmpty())
    <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
        {{ __('No payments recorded yet.') }}
    </p>
@else
    @php
        $canView = auth()->user()?->canManageMess() ?? false;
    @endphp
    {{-- mobile cards --}}
    <div class="space-y-3 md:hidden">
        @foreach ($payments as $payment)
            <div class="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    @if ($canView)
                        <a href="{{ route('mess.payments.show', $payment) }}" class="font-medium text-emerald-700 hover:underline">{{ $payment->member?->name ?? '—' }}</a>
                    @else
                        <span class="font-medium text-slate-900">{{ $payment->member?->name ?? '—' }}</span>
                    @endif
                    <x-method-pill :method="$payment->method" />
                </div>
                <div class="mt-1 flex items-center justify-between text-sm text-slate-600">
                    <span>{{ $payment->date->format('d-m-Y') }}</span>
                    <span class="font-semibold text-slate-900">{{ \App\Support\Money::taka($payment->amount) }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between">
                    <x-payment-type-pill :type="$payment->type" />
                    @if ($canView)
                        <div class="flex items-center gap-3">
                            <a href="{{ route('mess.payments.edit', $payment) }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('mess.payments.destroy', $payment) }}" onsubmit="return confirm('{{ __('Remove this payment?') }}');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-rose-700 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    {{-- desktop table --}}
    <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-4 py-3">{{ __('Member') }}</th>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Method') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Reference') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($payments as $payment)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            @if ($canView)
                                <a href="{{ route('mess.payments.show', $payment) }}" class="text-emerald-700 hover:underline">{{ $payment->member?->name ?? '—' }}</a>
                            @else
                                {{ $payment->member?->name ?? '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $payment->date->format('d-m-Y') }}</td>
                        <td class="px-4 py-3"><x-payment-type-pill :type="$payment->type" /></td>
                        <td class="px-4 py-3"><x-method-pill :method="$payment->method" /></td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ \App\Support\Money::taka($payment->amount) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $payment->reference ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canView)
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('mess.payments.edit', $payment) }}" class="btn btn-sm btn-ghost">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('mess.payments.destroy', $payment) }}" onsubmit="return confirm('{{ __('Remove this payment?') }}');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost text-rose-700">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
@endif
