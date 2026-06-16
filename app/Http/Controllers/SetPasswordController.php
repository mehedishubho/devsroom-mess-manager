<?php

namespace App\Http\Controllers;

use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $token = $request->query('token');
        $email = $request->query('email');

        $invitation = MemberInvitation::withoutGlobalScopes()
            ->where('token', $token)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invitation) {
            return redirect(url('/login'))->with('error', __('This invitation link is invalid or has expired.'));
        }

        return view('auth.set-password', ['email' => $email, 'token' => $token]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $invitation = MemberInvitation::withoutGlobalScopes()
            ->where('token', $request->input('token'))
            ->where('email', $request->input('email'))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $user = User::where('email', $invitation->email)->firstOrFail();
        $user->update([
            'password' => Hash::make($request->input('password')),
            'email_verified_at' => now(),
        ]);
        $invitation->update(['accepted_at' => now()]);

        Auth::login($user);

        return redirect()->route('my');
    }
}
