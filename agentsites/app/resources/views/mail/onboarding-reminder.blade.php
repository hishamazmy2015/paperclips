<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<body style="font-family: -apple-system, Segoe UI, Roboto, 'IBM Plex Sans Arabic', sans-serif; color: #1f1b16; margin: 0; padding: 24px; background: #fbf7f0;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px;">
        <p style="margin: 0 0 8px; color: #6b625a;">{{ config('app.name') }}</p>
        <h1 style="font-size: 20px; margin: 0 0 16px;">{{ __('platform.mail.reminder_intro', ['name' => $name]) }}</h1>
        <p style="margin: 0 0 20px;">{{ __('platform.mail.reminder_body') }}</p>
        <p style="text-align: center; margin: 0 0 20px;">
            <a href="{{ $resumeUrl }}" style="display: inline-block; background: #1f1b16; color: #fff; text-decoration: none; padding: 14px 24px; border-radius: 999px; font-weight: 600;">{{ __('platform.mail.reminder_cta') }}</a>
        </p>
        <p style="color: #6b625a; font-size: 13px; margin: 0;"><a href="{{ $optOutUrl }}" style="color: #6b625a;">{{ __('platform.mail.opt_out') }}</a></p>
    </div>
</body>
</html>
