<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Services\WalletLedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyWalletController extends Controller
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function index(Request $request): View
    {
        // Member identity comes from the authenticated user — never a URL param.
        $member = $request->user()->getMemberOrNull();

        if (! $member) {
            return view('my.no-member');
        }

        return view('my.wallet', [
            'member' => $member,
            'ledger' => $this->ledger->forMember($member),
        ]);
    }
}
