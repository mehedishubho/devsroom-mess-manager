<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\MonthlyClosing;
use Illuminate\View\View;

class MonthlyClosingController extends Controller
{
    public function index(): View
    {
        $closings = MonthlyClosing::query()
            ->with('closedBy')
            ->latest('year')
            ->latest('month')
            ->paginate(20);

        return view('mess.closings.index', compact('closings'));
    }

    public function show(MonthlyClosing $closing): View
    {
        $closing->load(['closedBy', 'memberSummaries.member', 'corrections.member']);

        return view('mess.closings.show', compact('closing'));
    }
}
