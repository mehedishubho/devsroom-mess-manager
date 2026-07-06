<?php

namespace App\Http\Controllers;

use App\Services\InstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RootController extends Controller
{
    public function __invoke(Request $request, InstallationService $installation): RedirectResponse
    {
        if ($installation->shouldRunSetup()) {
            return redirect()->route('setup.create');
        }

        if (! $request->user()) {
            return redirect()->guest('/login');
        }

        return redirect('/dashboard');
    }
}
