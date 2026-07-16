@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Edit category') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Rename the category. The slug is regenerated from the new name and must be unique within this mess.') }}</p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <form method="POST" action="{{ route('mess.categories.update', $category) }}" class="flex flex-col gap-4">
            @csrf
            @method('PATCH')

            <div class="flex flex-col gap-1">
                <label for="name" class="text-sm font-medium text-slate-900">{{ __('Name') }}<span class="text-red-600" aria-hidden="true">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}" required maxlength="100" class="input" autofocus />
                @error('name') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="kind" class="text-sm font-medium text-slate-900">{{ __('Kind') }}</label>
                    {{-- Defense-in-depth: this view is only reachable for non-default categories
                         (controller guard), but kind is fixed once a category has expenses —
                         show it read-only so the manager can see what bills it lands on. --}}
                    <input type="text" id="kind" value="{{ __(ucfirst($category->kind)) }}" disabled
                        class="input cursor-not-allowed bg-slate-50 text-slate-600" />
                    <p class="text-xs text-slate-500">{{ __('Kind cannot be changed once a category is in use.') }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="sort_order" class="text-sm font-medium text-slate-900">{{ __('Sort order') }}</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" class="input" />
                </div>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ __('Save category') }}
                </button>
                <a href="{{ route('mess.categories.index') }}" class="btn btn-ghost">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
