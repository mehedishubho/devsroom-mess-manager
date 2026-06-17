@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Notifications') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('All notifications are marked as read when you open this page.') }}</p>
    </header>
    @if ($notifications->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            {{ __('No notifications.') }}
        </p>
    @else
        <ul class="space-y-2">
            @foreach ($notifications as $n)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900">{{ \App\Support\NotificationType::LABELS[$n->type] ?? $n->type }}</p>
                        <p class="text-xs text-slate-500">{{ $n->created_at->format('d-m-Y H:i') }}</p>
                    </div>
                    @if (! empty($n->data))
                        <pre class="mt-2 overflow-x-auto rounded bg-slate-50 p-2 text-xs text-slate-700">{{ json_encode($n->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
