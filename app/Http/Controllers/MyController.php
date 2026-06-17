<?php

namespace App\Http\Controllers;

use App\Http\Requests\My\StoreMealOffRequest;
use App\Http\Requests\My\UpdateMyProfileRequest;
use App\Models\MealOffRequest;
use App\Models\Mess;
use App\Support\MealOffStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MyController extends Controller
{
    public function index(Request $request): View
    {
        $member = $request->user()->getMemberOrNull();
        $tab = $request->query('tab', 'profile');

        if (! $member) {
            return view('my.no-member');
        }

        $data = ['member' => $member, 'tab' => $tab];

        if ($tab === 'meals') {
            $data['mealEntries'] = $member->mealEntries()
                ->whereBetween('date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
                ->orderBy('date', 'desc')
                ->get();
        }

        if ($tab === 'meal-off') {
            $data['mealOffRequests'] = $member->mealOffRequests()
                ->orderBy('requested_at', 'desc')
                ->limit(20)
                ->get();
        }

        return view('my', $data);
    }

    public function updateProfile(UpdateMyProfileRequest $request): RedirectResponse
    {
        $member = $request->user()->getMemberOrNull();
        if (! $member) {
            return redirect()->route('my')->with('error', __('Your mess account is not set up.'));
        }

        $data = $request->validated();
        $photo = $data['photo'] ?? null;
        unset($data['photo']);

        $member->update($data);

        if ($photo) {
            $ext = $photo->getClientOriginalExtension();
            $path = "photos/{$member->id}.{$ext}";

            if ($member->photo_path) {
                Storage::disk('public')->delete($member->photo_path);
            }

            Storage::disk('public')->putFileAs(dirname($path), $photo, basename($path));
            $member->update(['photo_path' => $path]);
        }

        return redirect()->route('my', ['tab' => 'profile'])->with('success', __('Profile updated.'));
    }

    public function storeMealOff(StoreMealOffRequest $request): RedirectResponse
    {
        $member = $request->user()->getMemberOrNull();
        if (! $member) {
            return redirect()->route('my')->with('error', __('Your mess account is not set up.'));
        }

        MealOffRequest::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $member->id,
            'from_date' => $request->validated('from_date'),
            'to_date' => $request->validated('to_date'),
            'reason' => $request->validated('reason'),
            'status' => MealOffStatus::PENDING,
            'requested_at' => now(),
        ]);

        return redirect()->route('my', ['tab' => 'meal-off'])->with('success', __('Meal off request submitted. The manager will review it.'));
    }
}
