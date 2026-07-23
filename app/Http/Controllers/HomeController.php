<?php

namespace App\Http\Controllers;

use App\Models\Mess;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;

/**
 * HomeController — manager dashboard.
 *
 * Top: 6 stat cards (DashboardService::managerCards) + pending-meal-off banner.
 * Bottom: 4 report widgets — Members with Dues, Bazar vs Collection,
 * Expense Category Mix, Top Eaters (replaces the old 3 trend charts).
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboards,
    ) {}

    public function index(): View
    {
        $messId = Mess::activeId();

        return view('home', [
            'cards' => $this->dashboards->managerCards(),
            'pendingMealOff' => $this->dashboards->pendingMealOffCount(),
            'membersWithDues' => $this->dashboards->membersWithDues($messId),
            'bazarVsCollection' => $this->dashboards->bazarVsCollection($messId),
            'expenseCategoryMix' => $this->dashboards->expenseCategoryMix($messId),
            'topEaters' => $this->dashboards->topEaters($messId),
        ]);
    }
}
