@extends('layouts.pdf')

@section('title', __('Monthly Report') . ' — ' . ($period ?? ''))

@section('report-body')
    @php
        use App\Support\Money;
        // CRITICAL (D-19): member-side Monthly Report PDF is aggregates-only.
        // This view OMITS the per-member table entirely — peer dues/advances
        // are never rendered. Member identity is fixed (= self).
        $members = $data['members'] ?? [];
        $totalDue = collect($members)->sum('due');
        $totalAdvance = collect($members)->sum('advance_balance');
    @endphp

    <div class="totals-grid">
        <div><span class="label">{{ __('Members') }}:</span> {{ count($members) }}</div>
        <div><span class="label">{{ __('Meals') }}:</span> {{ number_format((float) ($data['total_meals'] ?? 0), 1) }}</div>
        <div><span class="label">{{ __('Meal rate') }}:</span> {{ Money::taka($data['meal_rate'] ?? 0) }} / {{ __('meal') }}</div>
        <div><span class="label">{{ __('Total bazar') }}:</span> {{ Money::taka($data['total_bazar'] ?? 0) }}</div>
        <div><span class="label">{{ __('Total fixed') }}:</span> {{ Money::taka($data['total_fixed'] ?? 0) }}</div>
        @php $pdfNet = collect($members)->sum(fn ($r) => ($r['advance_balance'] ?? 0) - ($r['due_balance'] ?? 0)); @endphp
        <div><span class="label">{{ __('Balance (net)') }}:</span> {{ ($pdfNet < 0 ? __('Owes') : __('Credit')).' '.Money::taka(abs($pdfNet)) }}</div>
    </div>

    <div class="label" style="margin-top: 12px; font-style: italic;">
        {{ __('This report shows mess-wide totals. Per-member detail is private — ask the manager for your own statement.') }}
    </div>
@endsection
