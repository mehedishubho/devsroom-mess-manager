<?php

namespace App\Http\Controllers;

use App\Http\Requests\My\StoreMealOffRequest;
use App\Http\Requests\My\UpdateMyProfileRequest;
use App\Http\Requests\My\ChangeMyPasswordRequest;
use App\Models\MealOffRequest;
use App\Models\Mess;
use App\Models\Payment;
use App\Services\MemberDashboardService;
use App\Support\MealOffStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MyController extends Controller
{
    public function __construct(
        private readonly MemberDashboardService $dashboards,
    ) {}

    public function index(Request $request): View
    {
        $member = $request->user()->getMemberOrNull();
        $tab = $request->query('tab', 'overview');

        if (! $member) {
            return view('my.no-member');
        }

        $data = ['member' => $member, 'tab' => $tab];

        if ($tab === 'overview') {
            $data['overview'] = $this->dashboards->overviewCards($request->user());
        }

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

        if ($tab === 'payments') {
            $data['payments'] = Payment::query()
                ->where('member_id', $member->id)
                ->with('enteredBy')
                ->latest('date')
                ->latest('id')
                ->paginate(30);
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
        unset($data['photo'], $data['current_password'], $data['new_password'], $data['new_password_confirmation']);

        $member->update($data);

        // Update User record name + email if provided
        $user = $request->user();
        $userData = [];
        if (isset($data['name'])) {
            $userData['name'] = $data['name'];
        }
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $userData['email'] = $data['email'];
        }
        if (! empty($userData)) {
            $user->update($userData);
        }

        // Handle password change
        if ($request->filled('new_password')) {
            $user->update([
                'password' => Hash::make($request->input('new_password')),
                'password_changed_at' => now(),
            ]);
        }

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

    public function showChangePassword(): View
    {
        return view('auth.change-password');
    }

    public function changePassword(ChangeMyPasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->input('password')),
            'password_changed_at' => now(),
        ]);

        return redirect()->intended(route('my'))->with('success', __('Password changed successfully.'));
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
