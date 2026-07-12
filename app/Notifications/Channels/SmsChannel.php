<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS ("phone number") channel. Two providers are supported, chosen by the
 * admin: Vonage (Nexmo) and Twilio. Recipient mobile comes from the member row,
 * normalized to international format. This is the "phone number" notification
 * channel — plain SMS, not a voice call.
 */
class SmsChannel extends Channel
{
    public function key(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return __('SMS (phone)');
    }

    public function send(User $recipient, string $type, array $data): array
    {
        $mobile = $this->recipientMobile($recipient);

        if (! $mobile) {
            return ['ok' => false, 'detail' => 'no mobile number on file'];
        }

        $message = NotificationMessage::for($type, $data, $this->messName());
        $text = "{$message->subject} — {$message->body}";

        try {
            $result = ($this->config()['provider'] ?? 'vonage') === 'twilio'
                ? $this->viaTwilio($mobile, $text)
                : $this->viaVonage($mobile, $text);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('SMS notification failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'detail' => 'sms error: ' . $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function viaVonage(string $to, string $text): array
    {
        $key = $this->config()['key'] ?? '';
        $secret = $this->config()['secret'] ?? '';
        $from = $this->config()['from'] ?: 'Mess';

        if (! $key || ! $secret) {
            return ['ok' => false, 'detail' => 'vonage credentials incomplete'];
        }

        $response = Http::asForm()->timeout(10)->post('https://rest.nexmo.com/sms/json', [
            'api_key' => $key,
            'api_secret' => $secret,
            'to' => $to,
            'from' => $from,
            'text' => $text,
        ]);

        $ok = $response->successful()
            && ($response->json('messages.0.status') === '0');

        if (! $ok) {
            Log::warning('Vonage SMS rejected', [
                'to' => $to, 'status' => $response->status(), 'body' => $response->body(),
            ]);

            return ['ok' => false, 'detail' => 'vonage rejected: ' . $response->body()];
        }

        return ['ok' => true, 'detail' => "sent to {$to}"];
    }

    /** @return array{ok: bool, detail: string} */
    private function viaTwilio(string $to, string $text): array
    {
        $sid = $this->config()['twilio_sid'] ?? '';
        $token = $this->config()['twilio_token'] ?? '';
        $from = $this->config()['from'] ?? '';

        if (! $sid || ! $token || ! $from) {
            return ['ok' => false, 'detail' => 'twilio credentials incomplete'];
        }

        $endpoint = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->timeout(10)
            ->post($endpoint, [
                'From' => $from,
                'To' => $to,
                'Body' => $text,
            ]);

        if (! $response->successful()) {
            Log::warning('Twilio SMS rejected', [
                'to' => $to, 'status' => $response->status(), 'body' => $response->body(),
            ]);

            return ['ok' => false, 'detail' => 'twilio rejected: ' . $response->body()];
        }

        return ['ok' => true, 'detail' => "sent to {$to}"];
    }
}
