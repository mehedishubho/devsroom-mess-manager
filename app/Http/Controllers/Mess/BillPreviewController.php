<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Services\BillPreviewService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillPreviewController extends Controller
{
    public function __construct(private readonly BillPreviewService $service) {}

    public function index(Request $request): View
    {
        [$year, $month] = $this->resolveYearMonth($request);

        $preview = $this->service->preview($year, $month);

        return view('mess.bill-preview.index', compact('preview', 'year', 'month'));
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveYearMonth(Request $request): array
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        return [$year, $month];
    }
}