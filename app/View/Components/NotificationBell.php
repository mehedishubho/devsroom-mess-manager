<?php

namespace App\View\Components;

use App\Services\NotificationService;
use Illuminate\View\Component;
use Illuminate\View\View;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    public function render(): View
    {
        $user = auth()->user();
        if ($user) {
            $this->unreadCount = app(NotificationService::class)->unreadCount($user->id);
        }

        return view('components.notification-bell');
    }
}
