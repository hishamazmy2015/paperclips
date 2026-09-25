<?php

declare(strict_types=1);

namespace App\Themes;

use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Themes from config/themes.php + resources/themes/{key}/ (spec §10). Each theme's views are
 * a Blade namespace "theme-{key}"; shared components live in resources/views/components/site.
 */
final class ThemeRegistry
{
    /** @return list<string> */
    public function keys(): array
    {
        /** @var array<string, array<string, mixed>> $themes */
        $themes = config('themes.themes', []);

        return array_keys($themes);
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * Themes that actually ship views (config may list themes that arrive in a later phase).
     *
     * @return list<string>
     */
    public function installed(): array
    {
        return array_values(array_filter($this->keys(), fn (string $key): bool => is_dir($this->path($key).'/views')));
    }

    public function default(): string
    {
        return (string) config('themes.default', 'atlas');
    }

    public function path(string $key): string
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown theme: {$key}");
        }

        return rtrim((string) config('themes.path'), '/').'/'.$key;
    }

    /** @return array<string, mixed> */
    public function manifest(string $key): array
    {
        $file = $this->path($key).'/manifest.json';
        if (! is_file($file)) {
            return [];
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return $manifest;
    }

    /** Blade view name for a theme page, e.g. view($registry->view('atlas', 'home')). */
    public function view(string $key, string $page): string
    {
        return 'theme-'.$key.'::'.$page;
    }

    /** CSS entry compiled by Vite once at deploy (spec §5, §10). */
    public function cssEntry(string $key): string
    {
        return 'resources/themes/'.$key.'/theme.css';
    }

    public function registerViewNamespaces(): void
    {
        foreach ($this->keys() as $key) {
            $views = $this->path($key).'/views';
            if (is_dir($views)) {
                View::addNamespace('theme-'.$key, $views);
            }
        }
    }
}
