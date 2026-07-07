<x-mail::message>
# {{ __('Welcome to the Mess!') }}

{{ __('An account has been created for you on the mess management system.') }}

{{ __('Your login credentials:') }}

- **{{ __('Email') }}:** {{ $user->email }}
- **{{ __('Password') }}:** `{{ $plainPassword }}`

<x-mail::button :url="url('/login')">
{{ __('Log in') }}
</x-mail::button>

{{ __('For security, please change your password after logging in.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
