<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = Audit::query()
            ->with('user')
            ->latest('created_at');

        if ($model = $request->query('model')) {
            $query->where('auditable_type', $model);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $audits = $query->paginate(20)->withQueryString();

        $models = Audit::query()
            ->distinct()
            ->pluck('auditable_type', 'auditable_type')
            ->filter()
            ->toArray();

        $users = User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $filters = $request->only(['model', 'user_id', 'from', 'to']);

        return view('mess.audit.index', compact('audits', 'models', 'users', 'filters'));
    }
}
