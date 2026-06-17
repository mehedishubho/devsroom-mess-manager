@if ($members->isEmpty())
    <x-empty-state
        :title="$search ? __('No members match your search.') : __('No members yet.')"
        :description="$search ? __('Try a different keyword or clear the search.') : __('Add the first member to start tracking meals and expenses.')"
        :actionLabel="$search ? null : __('Add member')"
        :actionRoute="$search ? null : route('mess.members.create')"
    />
@else
    <div class="space-y-3 md:hidden">
        @foreach ($members as $member)
            <x-member-card :member="$member" />
        @endforeach
    </div>

    <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm md:block">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Member') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Room/Seat') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Mobile') }}</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                    <th scope="col" class="relative px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @foreach ($members as $member)
                    <tr>
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('mess.members.show', $member) }}" class="flex items-center gap-3 min-h-[44px]">
                                @if ($member->photo_path)
                                    <img src="{{ Storage::disk('public')->url($member->photo_path) }}" alt="" class="h-8 w-8 rounded-full object-cover" />
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</div>
                                @endif
                                <span class="font-medium text-slate-900">{{ $member->name }}</span>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $member->room_or_seat ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $member->mobile ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <x-status-pill :variant="$member->status" />
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('mess.members.show', $member) }}" class="text-emerald-700 hover:underline min-h-[44px] inline-flex items-center">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (method_exists($members, 'links'))
        <div class="mt-4">{{ $members->links() }}</div>
    @endif
@endif
