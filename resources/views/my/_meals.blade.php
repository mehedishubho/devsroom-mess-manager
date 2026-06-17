<h2 class="text-lg font-semibold leading-tight text-slate-900">{{ __('My meals (this month)') }}</h2>
@if ($mealEntries->isEmpty())
    <p class="mt-2 text-sm text-slate-600">{{ __('No meals recorded for you this month yet.') }}</p>
@else
    <ul class="mt-3 divide-y divide-slate-200">
        @foreach ($mealEntries as $entry)
            <li class="flex items-center justify-between py-2 text-sm">
                <span class="text-slate-600">{{ $entry->date->format('d M, l') }}</span>
                <span class="text-slate-900">
                    @php
                        $meals = collect([
                            $entry->breakfast ? 'B' : null,
                            $entry->lunch ? 'L' : null,
                            $entry->dinner ? 'D' : null,
                        ])->filter()->implode(', ');
                    @endphp
                    {{ $meals ?: '—' }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
