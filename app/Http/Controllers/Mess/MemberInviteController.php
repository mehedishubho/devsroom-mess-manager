<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\InviteMemberRequest;
use App\Mail\SetPasswordMail;
use App\Models\MemberInvitation;
use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberInviteController extends Controller
{
    public function create(): View
    {
        return view('mess.members.invite');
    }

    public function store(InviteMemberRequest $request): RedirectResponse
    {
        $email = $request->validated()['email'];
        $messId = Mess::activeId();

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::before($email, '@'),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => null,
            ]
        );

        $user->assignRole(Role::firstOrCreate(['slug' => 'user'], ['name' => 'User']));

        $token = Str::random(48);
        MemberInvitation::create([
            'mess_id' => $messId,
            'email' => $email,
            'token' => $token,
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDay(),
        ]);

        $url = url(route('password.set.show', ['token' => $token, 'email' => $email]));
        Mail::to($email)->send(new SetPasswordMail($url));

        return redirect()
            ->route('mess.members.invite.create')
            ->with('success', __('Invitation sent to :email. They have 24 hours to set their password.', ['email' => $email]));
    }
}
