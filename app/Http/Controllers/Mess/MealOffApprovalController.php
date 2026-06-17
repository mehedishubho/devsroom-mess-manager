<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\ApproveMealOffRequest;
use App\Http\Requests\Mess\RejectMealOffRequest;
use App\Models\MealOffRequest;
use App\Services\MealOffApprovalService;
use App\Support\MealOffStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MealOffApprovalController extends Controller
{
    public function __construct(private readonly MealOffApprovalService $service) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', MealOffStatus::PENDING);

        $query = MealOffRequest::query()->with('member', 'actedBy');

        if ($tab === MealOffStatus::PENDING) {
            $query->where('status', MealOffStatus::PENDING)->orderBy('requested_at', 'asc');
        } else {
            $query->where('status', $tab)->orderBy('acted_at', 'desc');
        }

        $requests = $query->paginate(20)->withQueryString();

        $counts = [
            MealOffStatus::PENDING => MealOffRequest::where('status', MealOffStatus::PENDING)->count(),
            MealOffStatus::APPROVED => MealOffRequest::where('status', MealOffStatus::APPROVED)->count(),
            MealOffStatus::REJECTED => MealOffRequest::where('status', MealOffStatus::REJECTED)->count(),
        ];

        return view('mess.meal-off.index', compact('requests', 'tab', 'counts'));
    }

    public function approve(ApproveMealOffRequest $request, MealOffRequest $mealOffRequest): RedirectResponse
    {
        $this->service->approve($mealOffRequest, $request->user()->id);

        return redirect()
            ->route('mess.meal-off.index')
            ->with('success', __('Meal off approved for :name.', ['name' => $mealOffRequest->member?->name ?? 'member']));
    }

    public function reject(RejectMealOffRequest $request, MealOffRequest $mealOffRequest): RedirectResponse
    {
        $this->service->reject($mealOffRequest, $request->user()->id, $request->validated('rejection_reason'));

        return redirect()
            ->route('mess.meal-off.index')
            ->with('success', __('Meal off rejected for :name.', ['name' => $mealOffRequest->member?->name ?? 'member']));
    }
}
