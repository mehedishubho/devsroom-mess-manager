<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\WalletLedgerService;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(private readonly WalletLedgerService $ledger) {}

    public function show(Member $member): View
    {
        // Member is route-model-bound; the active-mess scope auto-404s a foreign member.
        return view('mess.members.wallet', [
            'member' => $member,
            'ledger' => $this->ledger->forMember($member),
        ]);
    }
}
