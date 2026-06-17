@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Add a member') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Create a new mess member. They will be set as active.') }}</p>
    </header>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.members.store') }}" enctype="multipart/form-data">
            @include('mess.members._form', ['member' => $member, 'method' => 'POST'])
        </form>
    </div>
@endsection
