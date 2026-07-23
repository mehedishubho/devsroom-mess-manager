<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect members (user role) to change their password on first login.
 *
 * After an admin creates a member with an auto-generated or manually-set
 * password, the member logs in for the first time with `password_changed_at
 * === null`. This middleware intercepts all member routes except the
 * change-password form itself and forces the member to set a new password.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Only apply to members (mess-member role), not admins/managers/super-admins.
        if (! $user->hasRole('mess-member')) {
            return $next($request);
        }

        // Don't redirect if the user has no Member record yet (pre-setup state).
        if (! $user->getMemberOrNull()) {
            return $next($request);
        }

        // Allow access if password has already been changed.
        if ($user->password_changed_at !== null) {
            return $next($request);
        }

        // Allow access to the change-password form itself, logout, and post-login.
        if ($request->routeIs('my.password.change', 'my.password.change.store', 'logout', 'post-login')) {
            return $next($request);
        }

        return redirect()->route('my.password.change')
            ->with('info', __('Please set a new password before continuing.'));
    }
}
