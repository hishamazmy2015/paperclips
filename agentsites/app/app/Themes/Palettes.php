<?php

declare(strict_types=1);

namespace App\Themes;

/** The six palettes as CSS variable sets (spec §10), plus a custom one from the agent's logo. */
final class Palettes
{
    /** @return array<string, array<string, string>> */
    public static function all(): array
    {
        /** @var array<string, array<string, string>> $palettes */
        $palettes = config('palettes.palettes', []);

        return $palettes;
    }

    /**
     * @param  array<string, mixed>  $custom
     * @return array<string, string> CSS variable name => colour
     */
    public static function cssVariables(string $key, array $custom = []): array
    {
        $all = self::all();
        $base = $all[$key] ?? $all['sand'] ?? [];
        if ($key === 'custom') {
            $base = $all['sand'] ?? [];
            foreach (['primary', 'secondary', 'accent', 'background', 'text'] as $name) {
                if (is_string($custom[$name] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $custom[$name]) === 1) {
                    $base[$name] = $custom[$name];
                }
            }
        }

        $vars = [];
        foreach ($base as $name => $value) {
            $vars['--c-'.str_replace('_', '-', $name)] = $value;
        }

        return $vars;
    }
}
