<?php

/*
|--------------------------------------------------------------------------
| Platform (spec §4)
|--------------------------------------------------------------------------
|
| PLATFORM_BASE_DOMAIN lives in .env and nowhere else. Every host the platform
| uses is derived here at runtime (re-cached by `platform:domain:change`), and
| the CI grep in infra/scripts/check-base-domain.sh fails on any other copy.
|
*/

$base = strtolower(trim((string) env('PLATFORM_BASE_DOMAIN', '')));

return [

    'base_domain' => $base,

    'hosts' => [
        'app' => 'app.'.$base,
        'admin' => 'admin.'.$base,
        'api' => 'api.'.$base,
        'cdn' => 'cdn.'.$base,
        'staging' => 'staging.'.$base,
    ],

    // Hosts that stay on the legacy platform (Caddy proxies them). Listed in DISCOVERY.md.
    'legacy_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('LEGACY_HOSTS', '')),
    ))),

    'locales' => ['ar', 'en'],
    'default_locale' => 'en',
    'currency' => 'AED',
    'vat_rate' => 0.05,
    'phone_default_region' => 'AE',
    'trial_days' => 14,

    // Local disk today; an S3-compatible disk is an env change (spec §5).
    'media_root' => env('MEDIA_ROOT', '/srv/platform/media'),

];
