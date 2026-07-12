<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\StoreMemberRequest;
use App\Http\Requests\Mess\UpdateMemberRequest;
use App\Models\Member;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $query = Member::query()
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'inactive' THEN 1 WHEN 'former' THEN 2 ELSE 3 END")
            ->orderBy('name');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('room_or_seat', 'like', "%{$search}%");
            });
        }

        $members = $query->paginate(50)->withQueryString();
        $activeCount = Member::where('status', 'active')->count();
        $search = (string) $request->query('q', '');

        return view('mess.members.index', compact('members', 'activeCount', 'search'));
    }

    public function create(): View
    {
        return view('mess.members.create', ['member' => new Member]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $photo = $data['photo'] ?? null;
        unset($data['photo'], $data['create_account'], $data['password'], $data['password_confirmation']);

        $data['mess_id'] = Mess::activeId();
        $member = Member::create($data);

        // Handle account creation
        if ($request->boolean('create_account')) {
            $email = $member->email;
            $plainPassword = $request->input('password', Str::random(12));

            $user = User::create([
                'name' => $member->name,
                'email' => $email,
                'password' => Hash::make($plainPassword),
            ]);

            $user->assignRole(Role::firstOrCreate(['slug' => 'user'], ['name' => 'User']));
            $member->update(['user_id' => $user->id]);

            // Send credentials email if mail configured and email exists
            if ($email && app()->bound('mailer') && count(config('mail.mailers.smtp', [])) > 0) {
                try {
                    Mail::to($email)->send(new \App\Mail\MemberCredentialsMail($user, $plainPassword));
                } catch (\Throwable) {
                    // Silently fail — credentials are shown on screen
                }
            }

            return redirect()
                ->route('mess.members.show', $member)
                ->with('success', __('Member :name added. Their login email is :email with password: :password', [
                    'name' => $member->name,
                    'email' => $email,
                    'password' => $plainPassword,
                ]));
        }

        if ($photo) {
            $this->storePhoto($member, $photo);
        }

        return redirect()
            ->route('mess.members.show', $member)
            ->with('success', __('Member :name added.', ['name' => $member->name]));
    }

    public function show(Member $member): View
    {
        $member->load(['mealEntries' => fn ($q) => $q->latest('date')->limit(30)]);
        $member->load(['mealOffRequests' => fn ($q) => $q->latest('requested_at')->limit(10)]);
        $member->load(['guestMeals' => fn ($q) => $q->latest('date')->limit(10)]);

        return view('mess.members.show', compact('member'));
    }

    public function edit(Member $member): View
    {
        return view('mess.members.edit', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $data = $request->validated();
        $photo = $data['photo'] ?? null;
        unset($data['photo']);

        $member->update($data);

        if ($photo) {
            $this->storePhoto($member, $photo);
        }

        return redirect()
            ->route('mess.members.show', $member)
            ->with('success', __('Member :name updated.', ['name' => $member->name]));
    }

    public function destroy(Member $member): RedirectResponse
    {
        // This method is wired to the PATCH .../deactivate route: it is the
        // reversible "hide from meal grid" action (status only, NOT a delete).
        // Permanent removal goes through delete() / forceDelete() below.
        $member->update(['status' => 'inactive']);

        return redirect()
            ->route('mess.members.index')
            ->with('success', __('Member :name marked as inactive.', ['name' => $member->name]));
    }

    /**
     * Soft-delete a member (sets deleted_at). Reversible via the database; the
     * member disappears from lists and the meal grid. Use deactivate() first if
     * you only want to drop them from the current month's denominator.
     */
    public function delete(Member $member): RedirectResponse
    {
        $name = $member->name;
        $member->delete();

        return redirect()
            ->route('mess.members.index')
            ->with('success', __('Member :name deleted. Their history is retained and can be restored if needed.', ['name' => $name]));
    }

    /**
     * Permanently remove a member and their direct profile data (photo). Guarded
     * by a dependency check: if the member has payments, meals, or expenses on
     * their behalf, we refuse — those records are part of the mess's immutable
     * financial history and must not be orphaned. Super-admin only (route gate).
     */
    public function forceDelete(Member $member): RedirectResponse
    {
        $blocking = $this->permanentDeleteBlockers($member);

        if ($blocking > 0) {
            return redirect()
                ->route('mess.members.show', $member)
                ->with('error', __(
                    'Cannot permanently delete :name — they have :count linked record(s) (meals, payments, or expenses). Soft-delete them instead to preserve the mess ledger.',
                    ['name' => $member->name, 'count' => $blocking]
                ));
        }

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
        }

        $name = $member->name;
        $member->forceDelete();

        return redirect()
            ->route('mess.members.index')
            ->with('success', __('Member :name permanently deleted.', ['name' => $name]));
    }

    /**
     * Count records that reference this member and would be orphaned by a hard
     * delete. A non-zero count means permanent deletion is unsafe.
     */
    private function permanentDeleteBlockers(Member $member): int
    {
        return $member->mealEntries()->count()
            + $member->mealOffRequests()->count()
            + $member->guestMeals()->count()
            + \App\Models\Payment::where('member_id', $member->id)->count()
            + \App\Models\Expense::where('purchased_by', $member->user_id)->count();
    }

    private function storePhoto(Member $member, UploadedFile $photo): void
    {
        $ext = $photo->getClientOriginalExtension();
        $path = "photos/{$member->id}.{$ext}";

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
        }

        Storage::disk('public')->putFileAs(
            dirname($path),
            $photo,
            basename($path),
        );

        $member->update(['photo_path' => $path]);
    }
}
