<?php

namespace App\Http\Middleware;

use App\Models\Mess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMessExists
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip the check on the onboarding routes themselves to avoid
        // self-redirect loops while creating the first mess.
        if ($request->routeIs('onboarding.*')) {
            return $next($request);
        }

        if (Mess::query()->doesntExist()) {
            return redirect()->route('onboarding.create');
        }

        return $next($request);
    }
}
