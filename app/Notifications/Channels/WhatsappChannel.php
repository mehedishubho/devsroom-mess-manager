<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp channel via the Twilio WhatsApp API (the most accessible WhatsApp
 * Business provider for self-hosters). Admin supplies the Twilio Account SID,
 * auth token, and a WhatsApp-enabled sender number. Recipient mobile is read
 * from the member row and normalized to international format.
 */
class WhatsappChannel extends Channel
{
    public function key(): string
    {
        return 'whatsapp';
    }

    public function label(): string
    {
        return __('WhatsApp');
    }

    public function send(User $recipient, string $type, array $data): array
    {
        $mobile = $this->recipientMobile($recipient);
        $config = $this->config();

        if (! $mobile) {
            return ['ok' => false, 'detail' => 'no mobile number on file'];
        }

        $sid = $config['sid'] ?? '';
        $token = $config['token'] ?? '';
        $from = $config['from'] ?? '';

        if (! $sid || ! $token || ! $from) {
            return ['ok' => false, 'detail' => 'whatsapp provider credentials incomplete'];
        }

        $message = NotificationMessage::for($type, $data, $this->messName());
        $endpoint = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(10)
                ->post($endpoint, [
                    'From' => $this->whatsappNumber($from),
                    'To' => $this->whatsappNumber($mobile),
                    'Body' => "{$message->subject}\n{$message->body}",
                ]);

            if ($response->successful()) {
                return ['ok' => true, 'detail' => "sent to {$mobile}"];
            }

            Log::warning('WhatsApp notification rejected', [
                'to' => $mobile, 'status' => $response->status(), 'body' => $response->body(),
            ]);

            return ['ok' => false, 'detail' => 'twilio rejected: '.$response->body()];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp notification failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'detail' => 'whatsapp error: '.$e->getMessage()];
        }
    }

    /**
     * Prefix a number with "whatsapp:" per the Twilio WhatsApp API. Avoids double
     * prefixing if the admin already entered a "whatsapp:" sender.
     */
    private function whatsappNumber(string $number): string
    {
        return str_starts_with($number, 'whatsapp:') ? $number : 'whatsapp:'.$number;
    }
}
