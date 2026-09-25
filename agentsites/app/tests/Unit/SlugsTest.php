<?php

declare(strict_types=1);

use App\Provisioning\Slugs;

// Spec §12.1: slugify + transliterate, §13 S3 suggestions.

it('derives slugs from names, transliterating Arabic', function (): void {
    expect(Slugs::fromName('Ahmed Al Falasi'))->toBe('ahmed-al-falasi')
        ->and(Slugs::fromName('  John   Smith '))->toBe('john-smith')
        ->and(Slugs::fromName('سارة المنصوري'))->toMatch('/^[a-z0-9-]{3,}$/')
        ->and(Slugs::fromName('!!'))->toStartWith('agent-')
        ->and(strlen(Slugs::fromName(str_repeat('abc ', 30))))->toBeLessThanOrEqual(40);
});
