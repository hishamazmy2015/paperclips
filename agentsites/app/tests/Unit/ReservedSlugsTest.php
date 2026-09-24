<?php

declare(strict_types=1);

use App\Platform\Hosts;

// Spec §12: platform hosts can never be taken by a tenant slug.

it('reserves every platform host label', function (): void {
    $reserved = config('reserved_slugs.reserved');

    foreach (array_keys(config('platform.hosts')) as $label) {
        expect($reserved)->toContain($label);
    }
    expect($reserved)->toContain('www')->toContain('mail')->toContain('internal');
});

it('accepts only lowercase slugs with digits and inner hyphens', function (): void {
    $pattern = config('reserved_slugs.pattern');

    expect(preg_match($pattern, 'ahmed-al-falasi'))->toBe(1)
        ->and(preg_match($pattern, 'a1'))->toBe(0)
        ->and(preg_match($pattern, 'Ahmed'))->toBe(0)
        ->and(preg_match($pattern, 'ahmed_falasi'))->toBe(0)
        ->and(preg_match($pattern, '-ahmed'))->toBe(0)
        ->and(preg_match($pattern, 'ahmed-'))->toBe(0)
        ->and(preg_match($pattern, str_repeat('a', 41)))->toBe(0);
});

it('never lets a reserved slug shadow a platform host', function (): void {
    config(['platform.base_domain' => 'example.test', 'platform.hosts' => ['app' => 'app.example.test']]);

    expect(Hosts::isPlatformHost(Hosts::tenant('app')))->toBeTrue();
});
