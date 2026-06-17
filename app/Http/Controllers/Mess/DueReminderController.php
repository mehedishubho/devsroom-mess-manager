<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\NotificationService;
use App\Support\MemberStatus;
use App\Support\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DueReminderController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(): View
    {
        $members = Member::query()
            ->where('status', MemberStatus::ACTIVE)
            ->with('advanceBalance')
            ->orderBy('name')
            ->get()
            ->filter(fn (Member $m) => (float) ($m->advanceBalance?->due_balance ?? 0) > 0);

        return view('mess.due-reminder.index', compact('members'));
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_ids' => ['required', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $count = 0;
        /** @var Member $member */
        foreach (Member::query()->whereIn('id', $data['member_ids'])->get() as $member) {
            $due = (float) ($member->advanceBalance?->due_balance ?? 0);
            if ($member->user_id && $due > 0) {
                $this->service->send($member->user, NotificationType::DUE_REMINDER, [
                    'due_balance' => $due,
                ]);
                $count++;
            }
        }

        return back()->with('success', __(':count reminder(s) sent.', ['count' => $count]));
    }
}
