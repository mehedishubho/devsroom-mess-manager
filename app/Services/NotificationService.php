<?php

namespace App\Services;

use App\Models\Mess;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    /**
     * Send a notification to one user.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(User $user, string $type, array $data = []): Notification
    {
        return Notification::create([
            'mess_id' => Mess::activeId(),
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * Broadcast a notification type to all manager users in the mess (admin + super-admin).
     * Used for close_complete and other manager-targeted events.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Notification>
     */
    public function broadcastToManagers(string $type, array $data = []): Collection
    {
        $recipients = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
            ->get();

        return $recipients->map(fn (User $u) => $this->send($u, $type, $data));
    }

    /**
     * @return Collection<int, Notification>
     */
    public function latestUnreadForUser(int $userId, int $limit = 10): Collection
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCount(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(Notification $notification): void
    {
        $notification->update(['read_at' => now()]);
    }

    public function markAllRead(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
