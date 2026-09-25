<?php

declare(strict_types=1);

use App\Provisioning\PhoneNormalizer;

// Spec §12.1: E.164 with the UAE as default region.

it('normalises UAE numbers in every common shape', function (string $raw): void {
    expect(app(PhoneNormalizer::class)->normalize($raw))->toBe('+971501234567');
})->with([
    '+971501234567',
    '0501234567',
    '050 123 4567',
    '00971501234567',
    '971501234567',
    '+971 50 123 4567',
    '٠٥٠١٢٣٤٥٦٧',
]);

it('keeps valid foreign numbers and rejects invalid ones', function (): void {
    $phones = app(PhoneNormalizer::class);

    expect($phones->normalize('+44 20 7946 0958'))->toBe('+442079460958')
        ->and($phones->normalize('+20 100 123 4567'))->toBe('+201001234567')
        ->and($phones->normalize('123'))->toBeNull()
        ->and($phones->normalize(''))->toBeNull()
        ->and($phones->normalize('not a phone'))->toBeNull();
});
