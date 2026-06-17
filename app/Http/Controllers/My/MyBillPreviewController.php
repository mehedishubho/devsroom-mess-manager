<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Services\BillPreviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyBillPreviewController extends Controller
{
    public function __construct(private readonly BillPreviewService $service) {}

    public function index(Request $request): View
    {
        $member = $request->user()->getMemberOrNull();
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $row = null;
        if ($member) {
            $row = $this->service->forMember($member->id, $year, $month);
        }

        return view('my._bill-preview', [
            'row' => $row,
            'member' => $member,
            'year' => $year,
            'month' => $month,
        ]);
    }
}
