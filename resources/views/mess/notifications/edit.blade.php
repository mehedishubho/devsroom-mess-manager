@extends('layouts.app')
@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-slate-900">{{ __('Notification channels') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Choose how the mess reaches its members. Multiple channels can be active at once; the in-app bell is always on.') }}</p>
    </header>

    <form method="POST" action="{{ route('mess.notifications.update') }}">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Channels') }}</h2>

            {{-- Email --}}
            <div class="mt-4 rounded-lg border border-slate-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="channels[email][enabled]" value="1" {{ ($config['channels']['email']['enabled'] ?? false) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                    <span class="font-medium text-slate-900">{{ __('Email') }}</span>
                </label>
                <p class="mt-1 text-xs text-slate-500">{{ __('Uses the application mail driver configured in .env. No extra credentials needed here.') }}</p>
            </div>

            {{-- Telegram --}}
            <div class="mt-3 rounded-lg border border-slate-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="channels[telegram][enabled]" value="1" {{ ($config['channels']['telegram']['enabled'] ?? false) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                    <span class="font-medium text-slate-900">{{ __('Telegram') }}</span>
                </label>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Bot token') }}</label>
                        <input type="password" name="channels[telegram][bot_token]" value="{{ $config['channels']['telegram']['bot_token'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Default chat / channel id') }}</label>
                        <input type="text" name="channels[telegram][default_chat_id]" value="{{ $config['channels']['telegram']['default_chat_id'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ __('Messages are posted to this chat via the Bot API. Per-member Telegram linking arrives in a later release.') }}</p>
            </div>

            {{-- WhatsApp --}}
            <div class="mt-3 rounded-lg border border-slate-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="channels[whatsapp][enabled]" value="1" {{ ($config['channels']['whatsapp']['enabled'] ?? false) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                    <span class="font-medium text-slate-900">{{ __('WhatsApp') }}</span>
                </label>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Provider') }}</label>
                        <select name="channels[whatsapp][provider]" class="input">
                            <option value="twilio" {{ (($config['channels']['whatsapp']['provider'] ?? '') === 'twilio') ? 'selected' : '' }}>Twilio</option>
                            <option value="meta" {{ (($config['channels']['whatsapp']['provider'] ?? '') === 'meta') ? 'selected' : '' }}>Meta Cloud API</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('From number') }}</label>
                        <input type="text" name="channels[whatsapp][from]" value="{{ $config['channels']['whatsapp']['from'] ?? '' }}" placeholder="+1234567890" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Account SID') }}</label>
                        <input type="text" name="channels[whatsapp][sid]" value="{{ $config['channels']['whatsapp']['sid'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Auth token') }}</label>
                        <input type="password" name="channels[whatsapp][token]" value="{{ $config['channels']['whatsapp']['token'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                </div>
            </div>

            {{-- SMS / phone --}}
            <div class="mt-3 rounded-lg border border-slate-200 p-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="channels[sms][enabled]" value="1" {{ ($config['channels']['sms']['enabled'] ?? false) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                    <span class="font-medium text-slate-900">{{ __('SMS (phone)') }}</span>
                </label>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Provider') }}</label>
                        <select name="channels[sms][provider]" class="input">
                            <option value="vonage" {{ (($config['channels']['sms']['provider'] ?? '') === 'vonage') ? 'selected' : '' }}>Vonage (Nexmo)</option>
                            <option value="twilio" {{ (($config['channels']['sms']['provider'] ?? '') === 'twilio') ? 'selected' : '' }}>Twilio</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Sender') }}</label>
                        <input type="text" name="channels[sms][from]" value="{{ $config['channels']['sms']['from'] ?? '' }}" placeholder="Mess" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Vonage key') }}</label>
                        <input type="text" name="channels[sms][key]" value="{{ $config['channels']['sms']['key'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Vonage secret') }}</label>
                        <input type="password" name="channels[sms][secret]" value="{{ $config['channels']['sms']['secret'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Twilio SID') }}</label>
                        <input type="text" name="channels[sms][twilio_sid]" value="{{ $config['channels']['sms']['twilio_sid'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-slate-700">{{ __('Twilio token') }}</label>
                        <input type="password" name="channels[sms][twilio_token]" value="{{ $config['channels']['sms']['twilio_token'] ?? '' }}" class="input" autocomplete="off" />
                    </div>
                </div>
            </div>
        </section>

        {{-- Routing matrix: which channels fire for each notification type --}}
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Routing') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Leave a row unchecked to use every active channel. Check specific channels to restrict a notification type.') }}</p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-4 font-semibold">{{ __('Notification') }}</th>
                            @foreach (App\Services\MessNotificationSettings::CHANNELS as $ch)
                                <th class="px-2 py-2 text-center font-semibold">{{ $channelLabels[$ch] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notificationTypes as $type)
                            @php $selected = $config['routing'][$type] ?? []; @endphp
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-4 font-medium text-slate-700">{{ $typeLabels[$type] ?? $type }}</td>
                                @foreach (App\Services\MessNotificationSettings::CHANNELS as $ch)
                                    <td class="px-2 py-2 text-center">
                                        <input type="checkbox" name="routing[{{ $type }}][]" value="{{ $ch }}" class="h-4 w-4 rounded border-slate-300 text-emerald-600" {{ in_array($ch, $selected, true) ? 'checked' : '' }} />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-6">
            <button type="submit" class="btn btn-primary">{{ __('Save notification settings') }}</button>
            <a href="{{ route('mess.settings.edit') }}" class="btn btn-secondary">{{ __('Back to mess settings') }}</a>
        </div>
    </form>
@endsection
