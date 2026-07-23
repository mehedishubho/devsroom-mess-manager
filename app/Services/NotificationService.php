<?php

namespace App\Services;

use App\Models\Mess;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(private readonly ChannelManager $channels) {}

    /**
     * Send a notification to one user. Always creates the canonical in-app
     * record, then fans out to the mess's enabled external channels
     * (email/WhatsApp/Telegram/SMS). Channel dispatch is fail-open — a
     * misconfigured or down provider never blocks the in-app notification or
     * the caller's transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(User $user, string $type, array $data = []): Notification
    {
        $notification = Notification::create([
            'mess_id' => Mess::activeId(),
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);

        $this->dispatchChannels($user, $type, $data);

        return $notification;
    }

    /**
     * Fan a notification out to the external channels for this type. Wrapped so
     * failures never escape — the in-app record is already written above.
     *
     * @param  array<string, mixed>  $data
     */
    private function dispatchChannels(User $user, string $type, array $data): void
    {
        try {
            $results = $this->channels->dispatch($user, $type, $data);

            foreach ($results as $channel => $result) {
                if (($result['ok'] ?? false) === false) {
                    Log::info('Notification channel did not deliver', [
                        'channel' => $channel,
                        'type' => $type,
                        'user_id' => $user->id,
                        'detail' => $result['detail'] ?? '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Notification channel fan-out failed', [
                'type' => $type, 'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);
        }
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
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['manager', 'super-admin']))
            ->when($activeMessId !== null, function ($q) use ($activeMessId) {
                // Super-admins are always included (cross-mess role). Managers must
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
