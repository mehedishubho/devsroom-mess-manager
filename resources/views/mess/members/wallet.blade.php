@extends('layouts.app')
@section('content')
    <div class="mb-4">
        <a href="{{ route('mess.advance-balances.index') }}" class="text-sm text-emerald-700 hover:underline">← {{ __('Member balances') }}</a>
    </div>
    @include('wallet._ledger', ['member' => $member, 'ledger' => $ledger])
    <div class="mt-4">
        <a href="{{ route('mess.advance-balances.adjust', $member) }}" class="btn btn-ghost btn-sm">{{ __('Adjust balance') }}</a>
        <a href="{{ route('mess.reports.member-statement', ['member_id' => $member->id]) }}" class="btn btn-ghost btn-sm">{{ __('Member statement') }}</a>
    </div>
@endsection
