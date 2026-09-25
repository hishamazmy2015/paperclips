<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<body style="font-family: -apple-system, Segoe UI, Roboto, 'IBM Plex Sans Arabic', sans-serif; color: #1f1b16; margin: 0; padding: 24px; background: #fbf7f0;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px;">
        <p style="margin: 0 0 8px; color: #6b625a;">{{ config('app.name') }}</p>
        <h1 style="font-size: 20px; margin: 0 0 16px;">{{ __('platform.mail.code_intro') }}</h1>
        <p style="font-size: 36px; letter-spacing: 8px; font-weight: 700; margin: 0 0 16px; direction: ltr; text-align: center;">{{ $code }}</p>
        <p style="margin: 0 0 20px;">{{ __('platform.mail.code_expires', ['minutes' => \App\Auth\OtpService::TTL_MINUTES]) }}</p>
        <p style="text-align: center; margin: 0 0 20px;">
            <a href="{{ $magicUrl }}" style="display: inline-block; background: #1f1b16; color: #fff; text-decoration: none; padding: 14px 24px; border-radius: 999px; font-weight: 600;">{{ __('platform.mail.code_link') }}</a>
        </p>
        <p style="color: #6b625a; font-size: 13px; margin: 0;">{{ __('platform.mail.ignore') }}</p>
    </div>
</body>
</html>
