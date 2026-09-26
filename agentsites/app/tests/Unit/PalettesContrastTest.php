<?php

declare(strict_types=1);

// Spec §10 / DECISIONS #56: every palette keeps WCAG AA contrast (≥ 4.5:1) for the colours the
// themes use as text — the Lighthouse accessibility gate fails on the first one that slips.

function relativeLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    $channels = [];
    foreach ([0, 2, 4] as $offset) {
        $c = hexdec(substr($hex, $offset, 2)) / 255;
        $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrastRatio(string $a, string $b): float
{
    $la = relativeLuminance($a);
    $lb = relativeLuminance($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

it('keeps every palette readable: text-on-background pairs stay at or above 4.5:1', function (): void {
    /** @var array<string, array<string, string>> $palettes */
    $palettes = config('palettes.palettes');
    expect($palettes)->toHaveCount(6);

    foreach ($palettes as $name => $p) {
        $pairs = [
            'primary on background' => [$p['primary'], $p['background']],
            'primary on surface' => [$p['primary'], $p['surface']],
            'primary_contrast on primary' => [$p['primary_contrast'], $p['primary']],
            'text on background' => [$p['text'], $p['background']],
            'text on surface' => [$p['text'], $p['surface']],
            'muted on background' => [$p['muted'], $p['background']],
            'muted on surface' => [$p['muted'], $p['surface']],
            'dark_text on dark_background' => [$p['dark_text'], $p['dark_background']],
            'dark_text on dark_surface' => [$p['dark_text'], $p['dark_surface']],
            'dark_muted on dark_background' => [$p['dark_muted'], $p['dark_background']],
            'dark_muted on dark_surface' => [$p['dark_muted'], $p['dark_surface']],
        ];
        foreach ($pairs as $label => [$fg, $bg]) {
            expect(contrastRatio($fg, $bg))->toBeGreaterThanOrEqual(4.5, "{$name}: {$label} ({$fg} on {$bg})");
        }
    }
});
