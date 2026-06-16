<?php

namespace App\Providers;

use App\Models\Mess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('en');

        // D-02: role-based post-login redirect.
        // - super-admin -> /onboarding (when no mess exists) or /dashboard
        // - admin (manager) -> /home
        // - user (member) -> /my
        // - anything else -> /
        config([
            'tyro-login.redirects.after_login' => function () {
                $user = Auth::user() ?? auth()->user();
                if (! $user) {
                    return '/';
                }
                if ($user->hasRole('super-admin')) {
                    if (! Mess::query()->exists()) {
                        return route('onboarding.create');
                    }

                    return '/dashboard';
                }
                if ($user->hasRole('admin')) {
                    return '/home';
                }
                if ($user->hasRole('user')) {
                    return '/my';
                }

                return '/';
            },
        ]);
    }
}
