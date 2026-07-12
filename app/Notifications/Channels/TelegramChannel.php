<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram channel via the Bot API. Admin supplies a bot token + a default
 * chat id (the mess group/channel or an admin chat). Per-user Telegram linking
 * is a future enhancement — v1 broadcasts to the configured default chat, which
 * is how most messes want Telegram (a shared "mess notices" channel).
 */
class TelegramChannel extends Channel
{
    public function key(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return __('Telegram');
    }

    public function send(User $recipient, string $type, array $data): array
    {
        $config = $this->config();
        $token = $config['bot_token'] ?? '';
        $chatId = $config['default_chat_id'] ?? '';

        if (! $token || ! $chatId) {
            return ['ok' => false, 'detail' => 'bot token or chat id not configured'];
        }

        $message = NotificationMessage::for($type, $data, $this->messName());
        $text = "*{$message->subject}*\n{$message->body}";

        try {
            $response = Http::withToken('')
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);

            if ($response->successful() && ($response->json('ok', false))) {
                return ['ok' => true, 'detail' => "sent to chat {$chatId}"];
            }

            Log::warning('Telegram notification rejected', [
                'chat' => $chatId, 'status' => $response->status(), 'body' => $response->body(),
            ]);

            return ['ok' => false, 'detail' => 'telegram api rejected: ' . $response->body()];
        } catch (\Throwable $e) {
            Log::warning('Telegram notification failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'detail' => 'telegram error: ' . $e->getMessage()];
        }
    }
}
