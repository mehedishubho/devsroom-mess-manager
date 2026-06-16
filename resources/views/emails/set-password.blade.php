<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Set your password') }}</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #1e293b; max-width: 560px; margin: 0 auto; padding: 20px;">
    <h1 style="font-size: 20px; color: #0f172a;">{{ __('Welcome to Devsroom Mess') }}</h1>
    <p>{{ __('Click the button below to set your password and activate your account. The link expires in 24 hours.') }}</p>
    <p style="margin: 24px 0;">
        <a href="{{ $url }}" style="background-color: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
            {{ __('Set my password') }}
        </a>
    </p>
    <p style="color: #64748b; font-size: 14px;">{{ __('If you did not request this, you can ignore this email.') }}</p>
</body>
</html>
