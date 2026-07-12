<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\NotificationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email channel. Uses the application's configured mail driver, so no per-mess
 * credentials are needed — the toggle just gates whether email is sent at all.
 * Fails open: if mail is not configured or the address is missing, it skips.
 */
class EmailChannel extends Channel
{
    public function key(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return __('Email');
    }

    public function send(User $recipient, string $type, array $data): array
    {
        $email = $this->recipientEmail($recipient);

        if (! $email) {
            return ['ok' => false, 'detail' => 'no email address on file'];
        }

        if (! config('mail.default') || config('mail.default') === 'log') {
            return ['ok' => false, 'detail' => 'no real mail driver configured'];
        }

        $message = NotificationMessage::for($type, $data, $this->messName());

        try {
            Mail::raw($message->body, function ($m) use ($email, $message) {
                $m->to($email)->subject($message->subject);
            });

            return ['ok' => true, 'detail' => "sent to {$email}"];
        } catch (\Throwable $e) {
            Log::warning('Email notification failed', [
                'type' => $type, 'email' => $email, 'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'detail' => 'mail error: '.$e->getMessage()];
        }
    }
}
