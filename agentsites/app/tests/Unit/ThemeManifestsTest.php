<?php

declare(strict_types=1);

use App\Themes\ThemeRegistry;

// Spec §10: each theme ships a manifest, pages, sections, six palettes, RTL and a preview.

it('ships a complete manifest, views, stylesheet and preview for every theme', function (): void {
    $registry = app(ThemeRegistry::class);
    expect($registry->keys())->toBe(['atlas', 'marina', 'palm']);

    foreach ($registry->keys() as $key) {
        $manifest = $registry->manifest($key);
        $path = $registry->path($key);

        expect($manifest['key'])->toBe($key)
            ->and($manifest['rtl'])->toBeTrue()
            ->and($manifest['pages'])->toBe(['home', 'listings', 'listing', 'about', 'area', 'contact', '404', 'paused'])
            ->and($manifest['sections'])->toBe(config('themes.sections'))
            ->and($manifest['palettes'])->toBe(config('themes.palettes'))
            ->and($manifest['dark_capable'])->toBe(config('themes.themes.'.$key.'.dark_capable'))
            ->and(is_file($path.'/theme.css'))->toBeTrue()
            ->and(is_file(public_path('themes/'.$key.'-preview.svg')))->toBeTrue();

        foreach ($manifest['pages'] as $page) {
            expect(is_file($path.'/views/'.$page.'.blade.php'))->toBeTrue("{$key}: missing page {$page}");
        }
        foreach ($manifest['sections'] as $section) {
            expect(is_file($path.'/views/sections/'.$section.'.blade.php'))->toBeTrue("{$key}: missing section {$section}");
        }
    }
});
