<?php

namespace App\Http\Controllers;

use App\Models\Mess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostLoginRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->hasRole('super-admin')) {
            if (! Mess::query()->exists()) {
                return redirect()->route('onboarding.create');
            }

            return redirect('/dashboard');
        }

        if ($user?->hasRole('manager')) {
            return redirect('/home');
        }

        if ($user?->hasRole('mess-member')) {
            return redirect('/my');
        }

        return redirect('/');
    }
}
