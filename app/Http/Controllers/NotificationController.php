<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): View
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(30)
            ->get();

        // Mark all as read once the user opens the notification center.
        $this->service->markAllRead($request->user()->id);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(Notification $notification, Request $request): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $this->service->markRead($notification);

        return back();
    }
}
