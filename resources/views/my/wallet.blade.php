@extends('layouts.app')
@section('content')
    <div class="mb-4">
        <a href="{{ route('my') }}" class="text-sm text-emerald-700 hover:underline">← {{ __('Back') }}</a>
    </div>
    @include('wallet._ledger', ['member' => $member, 'ledger' => $ledger])
@endsection
