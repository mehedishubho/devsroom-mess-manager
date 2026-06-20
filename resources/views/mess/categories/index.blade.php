@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Expense categories') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Manage the categories used for bazar and fixed expenses. Default categories cannot be deleted.') }}</p>
    </header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Kind') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Default') }}</th>
                            <th scope="col" class="relative px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($categories as $cat)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-900">{{ $cat->name }}</td>
                                <td class="px-4 py-3 text-sm"><x-status-pill :variant="$cat->kind" /></td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $cat->is_default ? __('Yes') : __('No') }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    @if (! $cat->is_default)
                                        <form method="POST" action="{{ route('mess.categories.destroy', $cat) }}" class="inline" onsubmit="return confirm('{{ __('Delete this category?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-700 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @else
                                        <span class="text-slate-400">{{ __('Locked') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-600">
                                    {{ __('No categories yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">{{ __('Add category') }}</h2>
                <form method="POST" action="{{ route('mess.categories.store') }}" class="mt-3">
                    @csrf
                    @include('mess.categories._form')
                </form>
            </div>
        </div>
    </div>
@endsection
