<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): View
    {
        $member = $request->user()->getMemberOrNull();

        if (! $member) {
            return view('my.no-member');
        }

        $payments = $this->service->listForMember($member->id);

        return view('my._payments', compact('payments'));
    }
}
