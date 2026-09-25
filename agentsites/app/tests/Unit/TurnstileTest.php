<?php

declare(strict_types=1);

use App\Auth\Turnstile;
use Illuminate\Support\Facades\Http;

it('is off without keys and verifies tokens with Cloudflare when on', function (): void {
    $turnstile = new Turnstile;
    expect($turnstile->enabled())->toBeFalse()->and($turnstile->verify(null, '1.2.3.4'))->toBeTrue();

    config(['providers.turnstile.site_key' => 'site', 'providers.turnstile.secret' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => true])->push(['success' => false])]);

    expect($turnstile->enabled())->toBeTrue()
        ->and($turnstile->verify('', '1.2.3.4'))->toBeFalse()
        ->and($turnstile->verify('tok', '1.2.3.4'))->toBeTrue()
        ->and($turnstile->verify('tok', '1.2.3.4'))->toBeFalse();

    Http::fake(fn () => throw new RuntimeException('down'));
    expect($turnstile->verify('tok', null))->toBeFalse();
});
