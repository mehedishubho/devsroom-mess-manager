<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\BulkSaveMealEntriesRequest;
use App\Services\MealGridService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MealGridController extends Controller
{
    public function __construct(private readonly MealGridService $service) {}

    public function index(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::now(config('app.timezone'))->startOfMonth();

        $data = $this->service->buildGridData($date);

        return view('mess.meals.index', [
            'rows' => $data['members'],
            'date' => $data['date']->toDateString(),
            'mealOffByMember' => $data['mealOffByMember'],
            'isClosed' => $data['is_closed'] ?? false,
        ]);
    }

    public function save(BulkSaveMealEntriesRequest $request): RedirectResponse
    {
        $date = Carbon::parse($request->validated('date'));
        $entries = $request->validated('entries');

        $this->service->bulkSave($date, $entries);

        return redirect()
            ->route('mess.meals.index', ['date' => $date->toDateString()])
            ->with('success', __('Meals saved for :date.', ['date' => $date->format('d M Y')]));
    }
}
