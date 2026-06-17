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
     * Broadcast a notification type to the manager users of the ACTIVE mess:
     *  - super-admin (cross-mess role — always notified when any mess closes), AND
     *  - admins whose Member row belongs to the active mess (WR-08).
     *
     * Without this scoping the previous query selected every admin across every
     * mess, leaking close-complete notifications cross-tenant.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Notification>
     */
    public function broadcastToManagers(string $type, array $data = []): Collection
    {
        $activeMessId = Mess::activeId();

        $recipients = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super-admin']))
            ->when($activeMessId !== null, function ($q) use ($activeMessId) {
                // Super-admins are always included (cross-mess role). Admins must
                // belong to the active mess via a Member row whose mess_id matches.
                $q->where(function ($inner) use ($activeMessId) {
                    $inner->whereHas('roles', fn ($r) => $r->where('slug', 'super-admin'))
                        ->orWhereHas('members', fn ($m) => $m->where('members.mess_id', $activeMessId));
                });
            })
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
