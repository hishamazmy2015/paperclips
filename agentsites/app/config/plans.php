<?php

/*
|--------------------------------------------------------------------------
| Plans (spec §12, §14) — plans are config; the `plans` table only mirrors
| this for reporting (platform:plans:sync). Prices are AED excluding 5% VAT
| and are PLACEHOLDERS until the product decision is recorded in DECISIONS.md.
|--------------------------------------------------------------------------
*/

return [

    'default' => 'trial',
    'trial_days' => 14,
    'currency' => 'AED',
    'grace_days' => 7,
    'dunning_days' => [0, 3, 6],

    'plans' => [

        'trial' => [
            'name' => ['en' => 'Trial', 'ar' => 'تجريبي'],
            'price_monthly' => 0,
            'price_yearly' => 0,
            'limits' => ['listings' => 100, 'custom_domains' => 1, 'members' => 1, 'storage_mb' => 2048],
            'features' => ['remove_branding' => true, 'custom_domain' => true, 'feeds' => true, 'analytics_pro' => true],
        ],

        'starter' => [
            'name' => ['en' => 'Starter', 'ar' => 'الأساسية'],
            'price_monthly' => 99,
            'price_yearly' => 990,
            'limits' => ['listings' => 25, 'custom_domains' => 0, 'members' => 1, 'storage_mb' => 512],
            'features' => ['remove_branding' => false, 'custom_domain' => false, 'feeds' => false, 'analytics_pro' => false],
        ],

        'pro' => [
            'name' => ['en' => 'Pro', 'ar' => 'الاحترافية'],
            'price_monthly' => 199,
            'price_yearly' => 1990,
            'limits' => ['listings' => 100, 'custom_domains' => 1, 'members' => 1, 'storage_mb' => 2048],
            'features' => ['remove_branding' => true, 'custom_domain' => true, 'feeds' => true, 'analytics_pro' => true],
        ],

        'brokerage' => [
            'name' => ['en' => 'Brokerage', 'ar' => 'الوساطة'],
            'price_monthly' => 799,
            'price_yearly' => 7990,
            'limits' => ['listings' => 1000, 'custom_domains' => 10, 'members' => 25, 'storage_mb' => 20480],
            'features' => ['remove_branding' => true, 'custom_domain' => true, 'feeds' => true, 'analytics_pro' => true],
        ],

    ],

];
