<?php

/*
|--------------------------------------------------------------------------
| Palettes (spec §9 branding.palette, §10). Each palette is a CSS variable set the theme
| references through Tailwind's @theme; the tenant's choice is inlined in <head>.
| Contrast: primary on primary-contrast and text on background stay ≥ 4.5:1 (WCAG AA).
|--------------------------------------------------------------------------
*/

return [

    'default' => 'sand',

    'palettes' => [
        'sand' => [
            'primary' => '#9a6b2f', 'primary_contrast' => '#ffffff', 'secondary' => '#3f3a33', 'accent' => '#c8963e',
            'background' => '#fbf7f0', 'surface' => '#ffffff', 'text' => '#1f1b16', 'muted' => '#6b625a', 'border' => '#e8dfd0',
            'dark_background' => '#161311', 'dark_surface' => '#211d19', 'dark_text' => '#f3ede4', 'dark_muted' => '#b3a89b', 'dark_border' => '#332d27',
        ],
        'navy' => [
            'primary' => '#1e3a8a', 'primary_contrast' => '#ffffff', 'secondary' => '#0f172a', 'accent' => '#c9a227',
            'background' => '#f8fafc', 'surface' => '#ffffff', 'text' => '#0f172a', 'muted' => '#526581', 'border' => '#e2e8f0',
            'dark_background' => '#0b1220', 'dark_surface' => '#111a2e', 'dark_text' => '#e5eefc', 'dark_muted' => '#9fb0c9', 'dark_border' => '#22304a',
        ],
        'emerald' => [
            'primary' => '#0f766e', 'primary_contrast' => '#ffffff', 'secondary' => '#134e4a', 'accent' => '#d4a017',
            'background' => '#f6fbf9', 'surface' => '#ffffff', 'text' => '#112220', 'muted' => '#4f6b67', 'border' => '#d9ebe6',
            'dark_background' => '#0c1917', 'dark_surface' => '#122421', 'dark_text' => '#e6f5f1', 'dark_muted' => '#9cbdb6', 'dark_border' => '#1f3833',
        ],
        'charcoal' => [
            'primary' => '#262626', 'primary_contrast' => '#ffffff', 'secondary' => '#525252', 'accent' => '#b45309',
            'background' => '#fafafa', 'surface' => '#ffffff', 'text' => '#171717', 'muted' => '#5f5f5f', 'border' => '#e5e5e5',
            'dark_background' => '#0f0f0f', 'dark_surface' => '#1a1a1a', 'dark_text' => '#f5f5f5', 'dark_muted' => '#a8a8a8', 'dark_border' => '#2a2a2a',
        ],
        'rose' => [
            'primary' => '#9f1239', 'primary_contrast' => '#ffffff', 'secondary' => '#4c0519', 'accent' => '#d4a373',
            'background' => '#fff8f9', 'surface' => '#ffffff', 'text' => '#2a0a12', 'muted' => '#7a5560', 'border' => '#f3dde3',
            'dark_background' => '#1a0b10', 'dark_surface' => '#241118', 'dark_text' => '#fbeef2', 'dark_muted' => '#c9a3ae', 'dark_border' => '#3b1f28',
        ],
        'gold' => [
            'primary' => '#8a6d1f', 'primary_contrast' => '#ffffff', 'secondary' => '#1c1917', 'accent' => '#d4af37',
            'background' => '#fffdf6', 'surface' => '#ffffff', 'text' => '#1c1917', 'muted' => '#6a5f4b', 'border' => '#efe6c9',
            'dark_background' => '#141210', 'dark_surface' => '#1e1a15', 'dark_text' => '#f7f2e4', 'dark_muted' => '#c2b48e', 'dark_border' => '#332d20',
        ],
    ],

];
