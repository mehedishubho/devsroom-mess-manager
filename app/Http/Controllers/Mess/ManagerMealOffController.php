<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreManagerMealOffRequest;
use App\Models\MealOffRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Support\MealOffStatus;
use Illuminate\Http\RedirectResponse;

class ManagerMealOffController extends Controller
{
    public function store(StoreManagerMealOffRequest $request, Member $member): RedirectResponse
    {
        MealOffRequest::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'from_date' => $request->validated('from_date'),
            'to_date' => $request->validated('to_date'),
            'reason' => $request->validated('reason'),
            'status' => MealOffStatus::PENDING,
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('mess.members.show', $member)
            ->with('success', __('Meal off request submitted for :name.', ['name' => $member->name]));
    }
}
