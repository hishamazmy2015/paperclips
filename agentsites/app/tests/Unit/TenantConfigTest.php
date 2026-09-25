<?php

declare(strict_types=1);

use App\Provisioning\ConfigMigrator;
use App\Provisioning\TenantConfig;

// Spec §9: schema validation, read-time defaults, normalisation.

beforeEach(function (): void {
    $this->config = app(TenantConfig::class);
});

it('accepts the minimal config (display_name + whatsapp)', function (): void {
    expect($this->config->validate(['identity' => ['display_name' => 'Ahmed'], 'contact' => ['whatsapp' => '+971501234567']]))->toBe([]);
});

it('reports missing required fields, bad patterns and unknown keys', function (): void {
    expect($this->config->validate(['identity' => ['display_name' => 'Ahmed']]))->not->toBe([])
        ->and($this->config->validate(['identity' => ['display_name' => 'Ahmed'], 'contact' => ['whatsapp' => '0501234567']]))->not->toBe([])
        ->and($this->config->validate(['identity' => ['display_name' => 'Ahmed', 'nope' => 1], 'contact' => ['whatsapp' => '+971501234567']]))->not->toBe([])
        ->and($this->config->validate(['identity' => ['display_name' => 'A'], 'contact' => ['whatsapp' => '+971501234567']]))->not->toBe([])
        ->and($this->config->validate(['identity' => ['display_name' => 'Ahmed'], 'contact' => ['whatsapp' => '+971501234567'], 'branding' => ['palette' => 'neon']]))->not->toBe([]);
});

it('validates nested structures the way the site expects them', function (): void {
    $valid = [
        'identity' => ['display_name' => 'Ahmed', 'tagline' => ['en' => 'x', 'ar' => 'y'], 'languages' => ['ar', 'en'], 'years_experience' => 8],
        'contact' => ['whatsapp' => '+971501234567', 'email' => 'a@example.com', 'socials' => ['instagram' => 'https://instagram.com/a']],
        'content' => ['service_areas' => ['Downtown'], 'sections' => [['key' => 'hero', 'enabled' => true]], 'why_me' => [['title' => ['en' => 't'], 'text' => ['en' => 'x'], 'icon' => 'map']]],
        'branding' => ['palette' => 'custom', 'custom_palette' => ['primary' => '#112233'], 'dark_mode' => 'auto'],
        'locale' => ['default' => 'ar', 'enabled' => ['ar', 'en'], 'currency' => 'AED'],
    ];
    expect($this->config->validate($valid))->toBe([]);

    $valid['content']['sections'][0]['key'] = 'carousel';
    expect($this->config->validate($valid))->not->toBe([]);
});

it('normalises away nulls, empty strings and empty arrays', function (): void {
    $out = $this->config->normalize([
        'identity' => ['display_name' => '  Ahmed ', 'agency_name' => '', 'brn' => null, 'languages' => []],
        'contact' => ['whatsapp' => '+971501234567', 'socials' => []],
        'content' => ['service_areas' => ['Downtown', '', ' Marina ']],
    ]);

    expect($out)->toBe([
        'identity' => ['display_name' => 'Ahmed'],
        'contact' => ['whatsapp' => '+971501234567'],
        'content' => ['service_areas' => ['Downtown', 'Marina']],
    ]);
});

it('merges defaults key by key and lets stored lists replace default lists', function (): void {
    $merged = TenantConfig::mergeDefaults(
        ['branding' => ['palette' => 'sand', 'dark_mode' => 'auto'], 'content' => ['sections' => [['key' => 'hero', 'enabled' => true], ['key' => 'blog', 'enabled' => false]]]],
        ['branding' => ['palette' => 'gold'], 'content' => ['sections' => [['key' => 'hero', 'enabled' => false]]]],
    );

    expect($merged['branding'])->toBe(['palette' => 'gold', 'dark_mode' => 'auto'])
        ->and($merged['content']['sections'])->toBe([['key' => 'hero', 'enabled' => false]]);
});

it('runs no-op migrations for the current schema version', function (): void {
    $migrator = new ConfigMigrator;
    expect($migrator->upgrade(['identity' => ['display_name' => 'A']]))->toBe(['identity' => ['display_name' => 'A']])
        ->and($migrator->upgrade(['_schema' => 1, 'x' => 1]))->toBe(['_schema' => 1, 'x' => 1]);
});
