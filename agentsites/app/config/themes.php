<?php

/*
|--------------------------------------------------------------------------
| Theme registry (spec §10). Each theme ships resources/themes/{key}/manifest.json,
| pages, six palettes as CSS variable sets and a wizard preview renderer.
| Section keys are toggleable and reorderable per tenant (config.content.sections).
|--------------------------------------------------------------------------
*/

return [

    'default' => 'atlas',
    'path' => resource_path('themes'),

    'palettes' => ['sand', 'navy', 'emerald', 'charcoal', 'rose', 'gold'],

    'sections' => ['hero', 'featured', 'about', 'areas', 'testimonials', 'services', 'stats', 'cta', 'contact', 'map', 'instagram', 'blog'],

    'themes' => [
        'atlas' => [
            'name' => 'Atlas',
            'description' => 'Full-bleed hero photo, sticky WhatsApp bar, listings grid, dark-capable',
            'dark_capable' => true,
        ],
        'marina' => [
            'name' => 'Marina',
            'description' => 'Split hero (photo left, text right), horizontal listing cards, light',
            'dark_capable' => false,
        ],
        'palm' => [
            'name' => 'Palm',
            'description' => 'Editorial: large type, minimal, testimonials-forward, gold accents',
            'dark_capable' => false,
        ],
    ],

];
