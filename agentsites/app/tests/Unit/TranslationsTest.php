<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

// Spec §22.8: Arabic and English are first-class everywhere — the two files must match key for key.

/**
 * @param  array<string, mixed>  $tree
 * @return list<string>
 */
function translationKeys(array $tree, string $prefix = ''): array
{
    $keys = [];
    foreach ($tree as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $keys = [...$keys, ...translationKeys($value, $path)];
        } else {
            $keys[] = $path;
        }
    }
    sort($keys);

    return $keys;
}

it('has the same keys in ar and en', function (): void {
    $en = translationKeys(require lang_path('en/platform.php'));
    $ar = translationKeys(require lang_path('ar/platform.php'));

    expect($ar)->toBe($en)->and($en)->not->toBeEmpty();
});

it('has no empty strings in either locale', function (): void {
    foreach (['en', 'ar'] as $locale) {
        $flat = Arr::dot(require lang_path($locale.'/platform.php'));
        foreach ($flat as $key => $value) {
            expect(trim((string) $value))->not->toBe('', "{$locale}: {$key} is empty");
        }
    }
});

it('serves the spec wording for the onboarding copy', function (): void {
    app()->setLocale('en');
    expect(__('platform.landing.headline'))->toBe('Your real estate website, live in 2 minutes.')
        ->and(__('platform.wizard.publish'))->toBe('Publish my website');

    app()->setLocale('ar');
    expect(__('platform.landing.headline'))->toBe('موقعك العقاري جاهز خلال دقيقتين.')
        ->and(__('platform.success.share_whatsapp'))->toBe('شارك عبر واتساب');
});
