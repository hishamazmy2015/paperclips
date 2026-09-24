<?php

/*
|--------------------------------------------------------------------------
| Tenant config defaults (spec §9). Merged into tenants.config at READ time,
| never stored, so changing a default updates every tenant at once. Keys mirror
| database/schemas/tenant-config.schema.json; required fields have no default.
|--------------------------------------------------------------------------
*/

return [

    'identity' => [
        'agency_name' => '',
        'license_no' => '',
        'brn' => '',
        'photo' => '',
        'logo' => '',
        'tagline' => ['en' => '', 'ar' => ''],
        'bio' => ['en' => '', 'ar' => ''],
        'languages' => ['ar', 'en'],
        'years_experience' => 0,
    ],

    'contact' => [
        'phone' => '',
        'email' => '',
        'office_address' => '',
        'socials' => [],
    ],

    'branding' => [
        'palette' => 'sand',
        'custom_palette' => [],
        'font_pair' => 'inter-plex-arabic',
        'hero_image' => '',
        'dark_mode' => 'auto',
    ],

    'content' => [
        'service_areas' => [],
        'specialties' => [],
        'about' => ['en' => '', 'ar' => ''],
        'why_me' => [],
        'sections' => [
            ['key' => 'hero', 'enabled' => true],
            ['key' => 'featured', 'enabled' => true],
            ['key' => 'about', 'enabled' => true],
            ['key' => 'areas', 'enabled' => true],
            ['key' => 'testimonials', 'enabled' => true],
            ['key' => 'services', 'enabled' => true],
            ['key' => 'stats', 'enabled' => false],
            ['key' => 'cta', 'enabled' => true],
            ['key' => 'contact', 'enabled' => true],
            ['key' => 'map', 'enabled' => false],
            ['key' => 'instagram', 'enabled' => false],
            ['key' => 'blog', 'enabled' => false],
        ],
    ],

    'listings' => [
        'show_demo_until_real' => true,
        'sort' => 'featured_desc,created_desc',
    ],

    'seo' => [
        'title_pattern' => '{name} — {agency} | {area}',
        'meta_description' => ['en' => '', 'ar' => ''],
        'og_image' => '',
        'gtag_id' => '',
        'meta_pixel_id' => '',
        'noindex' => true, // flipped to false by Publish (spec §12)
    ],

    'locale' => [
        'default' => 'en',
        'enabled' => ['ar', 'en'],
        'currency' => 'AED',
    ],

];
