<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Services\MessNotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Lets each logged-in user choose their own notification channels from the
 * subset the mess admin has enabled. Members and managers both land here —
 * the available channels are always capped at what the admin turned on.
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly MessNotificationSettings $settings) {}

    public function edit(): View
    {
        $user = auth()->user();

        $available = collect($this->settings->read()['channels'] ?? [])
            ->filter(fn ($block) => ($block['enabled'] ?? false) === true);

        return view('notification_preferences.edit', [
            'available' => $available,
            'selected' => $user->preferredChannels(),
            'hasPreference' => $user->preferredChannels() !== null,
            'channelLabels' => [
                MessNotificationSettings::CHANNEL_EMAIL => __('Email'),
                MessNotificationSettings::CHANNEL_TELEGRAM => __('Telegram'),
                MessNotificationSettings::CHANNEL_WHATSAPP => __('WhatsApp'),
                MessNotificationSettings::CHANNEL_SMS => __('SMS (phone)'),
            ],
        ]);
    }

    public function update(UpdateNotificationPreferenceRequest $request): RedirectResponse
    {
        $adminEnabled = collect($this->settings->read()['channels'] ?? [])
            ->filter(fn ($block) => ($block['enabled'] ?? false) === true)
            ->keys()
            ->all();

        // Defense in depth: cap the user's choice to channels the admin enabled.
        $chosen = array_values(array_intersect(
            $request->validated()['channels'] ?? [],
            $adminEnabled,
        ));

        auth()->user()->setPreferredChannels($chosen);

        return redirect()
            ->route('notification-preferences.edit')
            ->with('success', __('Your notification preferences were saved.'));
    }
}
