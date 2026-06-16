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
        if (Mess::withoutGlobalScopes()->doesntExist()) {
            return redirect()->route('onboarding.create');
        }

        return $next($request);
    }
}
