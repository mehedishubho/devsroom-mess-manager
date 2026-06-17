<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = Member::query()
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'inactive' THEN 1 WHEN 'former' THEN 2 ELSE 3 END")
            ->orderBy('name');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('room_or_seat', 'like', "%{$search}%");
            });
        }

        $members = $query->limit(50)->get();
        $activeCount = Member::where('status', 'active')->count();

        return view('mess.members._list', compact('members', 'activeCount', 'search'));
    }
}
