@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Edit member') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Update :name\'s information.', ['name' => $member->name]) }}</p>
    </header>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.members.update', $member) }}" enctype="multipart/form-data">
            @include('mess.members._form', ['member' => $member, 'method' => 'PATCH'])
        </form>
    </div>
@endsection
