<?php

declare(strict_types=1);

use App\Platform\Hosts;

// Spec §4: every host is derived from the one configured base domain.

beforeEach(function (): void {
    config([
        'platform.base_domain' => 'example.test',
        'platform.hosts' => [
            'app' => 'app.example.test',
            'admin' => 'admin.example.test',
            'api' => 'api.example.test',
            'cdn' => 'cdn.example.test',
            'staging' => 'staging.example.test',
        ],
        'platform.legacy_hosts' => ['legacy.example.test', 'www.legacy.example.test'],
    ]);
});

it('derives the platform hosts from the base domain', function (): void {
    expect(Hosts::base())->toBe('example.test')
        ->and(Hosts::app())->toBe('app.example.test')
        ->and(Hosts::admin())->toBe('admin.example.test')
        ->and(Hosts::api())->toBe('api.example.test')
        ->and(Hosts::cdn())->toBe('cdn.example.test')
        ->and(Hosts::staging())->toBe('staging.example.test');
});

it('builds tenant hosts and urls from a slug', function (): void {
    expect(Hosts::tenant('Ahmed-Al-Falasi'))->toBe('ahmed-al-falasi.example.test')
        ->and(Hosts::url(Hosts::tenant('sara'), 'ar/listings'))->toBe('https://sara.example.test/ar/listings');
});

it('recognises platform hosts, ignoring case and port', function (): void {
    expect(Hosts::isPlatformHost('example.test'))->toBeTrue()
        ->and(Hosts::isPlatformHost('APP.example.test:8444'))->toBeTrue()
        ->and(Hosts::isPlatformHost('ahmed.example.test'))->toBeFalse()
        ->and(Hosts::isPlatformHost('agent.ae'))->toBeFalse();
});

it('recognises legacy hosts from LEGACY_HOSTS', function (): void {
    expect(Hosts::isLegacyHost('WWW.legacy.example.test'))->toBeTrue()
        ->and(Hosts::isLegacyHost('app.example.test'))->toBeFalse();
});

it('reads the real config from the environment', function (): void {
    // phpunit.xml sets PLATFORM_BASE_DOMAIN=example.test; config/platform.php derives the rest.
    /** @var array<string, mixed> $fresh */
    $fresh = require config_path('platform.php');

    expect($fresh['base_domain'])->toBe('example.test')
        ->and($fresh['hosts']['app'])->toBe('app.example.test')
        ->and($fresh['locales'])->toBe(['ar', 'en'])
        ->and($fresh['currency'])->toBe('AED');
});

it('derives the public origin of any host from app.url when there is no request to copy', function (): void {
    config(['app.url' => 'https://app.example.test']);
    expect(Hosts::publicOrigin('ahmed.example.test'))->toBe('https://ahmed.example.test');
    config(['app.url' => 'http://app.example.test:8123']);
    expect(Hosts::publicOrigin('ahmed.example.test'))->toBe('http://ahmed.example.test:8123');
    config(['app.url' => 'http://app.example.test:80']);
    expect(Hosts::publicOrigin('ahmed.example.test'))->toBe('http://ahmed.example.test');
    config(['app.url' => '']);
    expect(Hosts::publicOrigin('ahmed.example.test'))->toBe('https://ahmed.example.test');
});
