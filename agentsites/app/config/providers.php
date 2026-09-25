<?php

/*
|--------------------------------------------------------------------------
| External providers (spec §6). Every provider sits behind an interface with a
| no-network fallback, so an unset key never breaks a flow: content falls back
| to templates, WhatsApp to the null/file notifier, Turnstile switches off.
|--------------------------------------------------------------------------
*/

return [

    'content' => [
        // template | claude
        'generator' => env('CONTENT_GENERATOR', 'template'),
        'anthropic' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
            'timeout' => (float) env('ANTHROPIC_TIMEOUT', 60),
        ],
    ],

    'whatsapp' => [
        // null (log only) | file (storage/app/private/whatsapp-sink, dev/staging) | dialog360
        'provider' => env('WHATSAPP_PROVIDER', 'null'),
        'sender' => env('WHATSAPP_SENDER'),
        'dialog360' => [
            'key' => env('DIALOG360_API_KEY'),
            'base_url' => env('DIALOG360_BASE_URL', 'https://waba-v2.360dialog.io'),
        ],
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Sign-in code limits per 10 minutes (spec §17). Production keeps the defaults; the
    // Playwright timing runs raise the per-IP ones because every run comes from one address.
    'otp' => [
        'sends_per_identifier' => (int) env('OTP_SENDS_PER_IDENTIFIER', 3),
        'sends_per_ip' => (int) env('OTP_SENDS_PER_IP', 10),
        'verifies_per_ip' => (int) env('OTP_VERIFIES_PER_IP', 30),
    ],

];
