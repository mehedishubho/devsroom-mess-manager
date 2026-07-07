<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\BulkSaveMonthlyMealsRequest;
use App\Services\MealMonthlyGridService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MealMonthlyGridController extends Controller
{
    public function __construct(
        private readonly MealMonthlyGridService $service,
    ) {}

    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->query('month'))
            : Carbon::now(config('app.timezone'));

        $data = $this->service->buildMonthlyGridData($month);

        $monthStr = $month->format('Y-m');

        return view('mess.meals.monthly', array_merge($data, [
            'monthStr' => $monthStr,
        ]));
    }

    public function save(BulkSaveMonthlyMealsRequest $request): RedirectResponse
    {
        $month = Carbon::parse($request->validated('month'));
        $entries = $request->validated('entries', []);

        $this->service->bulkSaveMonthly($month, $entries);

        return redirect()
            ->route('mess.meals.monthly', ['month' => $month->format('Y-m')])
            ->with('success', __('Meals saved for :month.', ['month' => $month->translatedFormat('F Y')]));
    }
}
