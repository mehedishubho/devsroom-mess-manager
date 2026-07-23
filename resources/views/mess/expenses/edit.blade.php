@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Edit expense') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $expense->category?->name ?? '—' }} — {{ $expense->date->format('d M Y') }}</p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.expenses.update', $expense) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @include('mess.expenses._form')

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ __('Save changes') }}
                </button>
                <a href="{{ route('mess.expenses.show', $expense) }}" class="btn btn-ghost">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
