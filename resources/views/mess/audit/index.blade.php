@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Audit log') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Every change to mess data, recorded with the user, timestamp, and before/after values.') }}</p>
    </header>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
        <h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('Filters') }}</h2>
        <form method="GET" action="{{ route('mess.audit') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-900" for="model">{{ __('Model') }}</label>
                <select name="model" id="model"
                    style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23475569%22 stroke-width=%222%22><path d=%22m19.5 8.25-7.5 7.5-7.5-7.5%22/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.25rem;"
                    class="input appearance-none pr-10">
                    <option value="">{{ __('All models') }}</option>
                    @foreach ($models as $class)
                        <option value="{{ $class }}" @selected(($filters['model'] ?? '') === $class)>{{ class_basename($class) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-900" for="user_id">{{ __('User') }}</label>
                <select name="user_id" id="user_id"
                    style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23475569%22 stroke-width=%222%22><path d=%22m19.5 8.25-7.5 7.5-7.5-7.5%22/></svg>'); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.25rem;"
                    class="input appearance-none pr-10">
                    <option value="">{{ __('All users') }}</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['user_id'] ?? '') == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-900" for="from">{{ __('From date') }}</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="input input-date">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-slate-900" for="to">{{ __('To date') }}</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="input input-date">
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 md:col-span-4">
                <button type="submit" class="btn btn-primary">{{ __('Apply filters') }}</button>
                <a href="{{ route('mess.audit') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
            </div>
        </form>
    </div>

    <div class="mt-6">
        @if ($audits->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center md:p-12">
                <p class="text-base font-medium text-slate-900">{{ __('No audit entries match your filters.') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('Try clearing the filters or check back after a mess change is made.') }}</p>
            </div>
        @else
            <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('When') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Who') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('What') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Action') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach ($audits as $audit)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-900">{{ $audit->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-900">{{ $audit->user?->name ?? __('System') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ class_basename($audit->auditable_type) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $audit->event }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    @if ($audit->old_values || $audit->new_values)
                                        <details>
                                            <summary class="cursor-pointer text-emerald-700 hover:underline">{{ __('View diff') }}</summary>
                                            <pre class="mt-2 overflow-x-auto rounded bg-slate-50 p-2 text-xs text-slate-700">@json(['old' => $audit->old_values, 'new' => $audit->new_values], JSON_PRETTY_PRINT)</pre>
                                        </details>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="space-y-3 md:hidden">
                @foreach ($audits as $audit)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-xs text-slate-500">{{ $audit->created_at->format('Y-m-d H:i') }}</p>
                        <dl class="mt-2 grid grid-cols-2 gap-1 text-sm">
                            <dt class="text-slate-500">{{ __('Who') }}</dt>
                            <dd class="text-slate-900">{{ $audit->user?->name ?? __('System') }}</dd>
                            <dt class="text-slate-500">{{ __('What') }}</dt>
                            <dd class="text-slate-900">{{ class_basename($audit->auditable_type) }}</dd>
                            <dt class="text-slate-500">{{ __('Action') }}</dt>
                            <dd class="text-slate-900">{{ $audit->event }}</dd>
                        </dl>
                        @if ($audit->old_values || $audit->new_values)
                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm text-emerald-700">{{ __('View diff') }}</summary>
                                <pre class="mt-2 overflow-x-auto rounded bg-slate-50 p-2 text-xs text-slate-700">@json(['old' => $audit->old_values, 'new' => $audit->new_values], JSON_PRETTY_PRINT)</pre>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $audits->links() }}</div>
        @endif
    </div>
@endsection
