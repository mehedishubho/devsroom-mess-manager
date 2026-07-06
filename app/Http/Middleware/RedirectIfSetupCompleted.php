<?php

namespace App\Http\Middleware;

use App\Services\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSetupCompleted
{
    public function __construct(private readonly InstallationService $installation)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installation->shouldRunSetup()) {
            if ($request->isMethod('get')) {
                return redirect('/dashboard');
            }

            abort(404);
        }

        return $next($request);
    }
}
