@extends('layouts.app')

@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Restore backup') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('Backup file: :path', ['path' => $path]) }}
        </p>
    </header>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        @include('dashboard.backups._restore_form', ['path' => $path, 'expectedMessName' => $expectedMessName])
    </div>
@endsection
