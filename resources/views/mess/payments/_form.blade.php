@csrf
@if (isset($payment) && $payment)
    @method('PATCH')
@endif
<div>
    <span class="block text-sm font-medium text-slate-700">{{ __('Payment type') }}</span>
    <div class="mt-1 inline-flex rounded-md border border-slate-300 bg-white p-0.5 text-sm">
        <label class="cursor-pointer">
            <input type="radio" name="type" value="{{ \App\Support\PaymentType::BILL_PAYMENT }}" class="peer sr-only" @checked(($payment->type ?? \App\Support\PaymentType::BILL_PAYMENT) === \App\Support\PaymentType::BILL_PAYMENT) />
            <span class="inline-flex min-h-[44px] items-center rounded px-3 peer-checked:bg-slate-900 peer-checked:text-white">{{ __('Bill payment') }}</span>
        </label>
        <label class="cursor-pointer">
            <input type="radio" name="type" value="{{ \App\Support\PaymentType::ADVANCE_DEPOSIT }}" class="peer sr-only" @checked(($payment->type ?? null) === \App\Support\PaymentType::ADVANCE_DEPOSIT) />
            <span class="inline-flex min-h-[44px] items-center rounded px-3 peer-checked:bg-sky-600 peer-checked:text-white">{{ __('Advance deposit') }}</span>
        </label>
    </div>
    @error('type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
<div>
    <label for="member_id" class="block text-sm font-medium text-slate-700">{{ __('Member') }}</label>
    <select name="member_id" id="member_id" class="input mt-1 @error('member_id') input-error @enderror">
        <option value="">{{ __('Select member') }}</option>
        @foreach ($members as $id => $name)
            <option value="{{ $id }}" @selected(old('member_id', $payment->member_id ?? null) == $id)>{{ $name }}</option>
        @endforeach
    </select>
    @error('member_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
<div>
    <label for="date" class="block text-sm font-medium text-slate-700">{{ __('Date') }}</label>
    <input type="date" name="date" id="date" value="{{ old('date', isset($payment) && $payment ? $payment->date->format('Y-m-d') : now()->toDateString()) }}" class="input input-date mt-1 @error('date') input-error @enderror" />
    @error('date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
<div>
    <label for="amount" class="block text-sm font-medium text-slate-700">{{ __('Amount (BDT)') }}</label>
    <input type="number" name="amount" id="amount" min="0.01" step="0.01" value="{{ old('amount', $payment->amount ?? '') }}" class="input mt-1 @error('amount') input-error @enderror" />
    @error('amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
<div>
    <label for="method" class="block text-sm font-medium text-slate-700">{{ __('Method') }}</label>
    <select name="method" id="method" class="input mt-1 @error('method') input-error @enderror">
        @foreach (\App\Support\PaymentMethod::ALL as $m)
            <option value="{{ $m }}" @selected(old('method', $payment->method ?? \App\Support\PaymentMethod::CASH) === $m)>{{ \App\Support\PaymentMethod::LABELS[$m] }}</option>
        @endforeach
    </select>
    @error('method') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
<div>
    <label for="reference" class="block text-sm font-medium text-slate-700">{{ __('Reference') }} <span class="text-xs text-slate-500">{{ __('(required for non-cash)') }}</span></label>
    <input type="text" name="reference" id="reference" maxlength="100" value="{{ old('reference', $payment->reference ?? '') }}" class="input mt-1 @error('reference') input-error @enderror" />
    @error('reference') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
<div>
    <label for="notes" class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
    <textarea name="notes" id="notes" rows="3" maxlength="1000" class="input mt-1 @error('notes') input-error @enderror">{{ old('notes', $payment->notes ?? '') }}</textarea>
    @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
