<?php

namespace App\Providers;

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
        // - super-admin -> /dashboard (Tyro admin UI)
        // - admin (manager) -> /home
        // - user (member) -> /my
        // - anything else -> /
        config([
            'tyro-login.redirects.after_login' => function () {
                $user = Auth::user();
                if (! $user) {
                    return '/';
                }
                if ($user->hasRole('super-admin')) {
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
