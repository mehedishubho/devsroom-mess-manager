<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\UpdateMessNotificationSettingsRequest;
use App\Services\MessNotificationSettings;
use App\Support\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin configuration screen for multi-channel notifications. Admins toggle
 * email / WhatsApp / Telegram / SMS, store provider credentials (encrypted at
 * rest by the settings layer in a future hardening pass), and route each
 * notification type to the channels it should fire on.
 */
class MessNotificationController extends Controller
{
    public function __construct(private readonly MessNotificationSettings $settings) {}

    public function edit(): View
    {
        $config = $this->settings->read();

        return view('mess.notifications.edit', [
            'config' => $config,
            'channelLabels' => $this->channelLabels(),
            'notificationTypes' => NotificationType::ALL,
            'typeLabels' => NotificationType::LABELS,
        ]);
    }

    public function update(UpdateMessNotificationSettingsRequest $request): RedirectResponse
    {
        $this->settings->write($request->normalizedConfig());

        return redirect()
            ->route('mess.notifications.edit')
            ->with('success', __('Notification channels updated.'));
    }

    /**
     * Human labels for the four external channels, shown as the routing matrix
     * column headers.
     *
     * @return array<string, string>
     */
    private function channelLabels(): array
    {
        return [
            MessNotificationSettings::CHANNEL_EMAIL => __('Email'),
            MessNotificationSettings::CHANNEL_TELEGRAM => __('Telegram'),
            MessNotificationSettings::CHANNEL_WHATSAPP => __('WhatsApp'),
            MessNotificationSettings::CHANNEL_SMS => __('SMS'),
        ];
    }
}
