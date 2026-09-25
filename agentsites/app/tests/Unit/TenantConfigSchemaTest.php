<?php

declare(strict_types=1);

// Spec §9: the tenant config schema and its defaults stay in step.

/** @return array<string, mixed> */
function tenantSchema(): array
{
    $json = file_get_contents(database_path('schemas/tenant-config.schema.json'));

    return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
}

it('is a valid draft 2020-12 schema requiring identity and contact', function (): void {
    $schema = tenantSchema();

    expect($schema['$schema'])->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($schema['required'])->toBe(['identity', 'contact'])
        ->and($schema['properties']['identity']['required'])->toBe(['display_name'])
        ->and($schema['properties']['contact']['required'])->toBe(['whatsapp']);
});

it('validates WhatsApp numbers as E.164', function (): void {
    $pattern = '/'.tenantSchema()['properties']['contact']['properties']['whatsapp']['pattern'].'/';

    expect(preg_match($pattern, '+971501234567'))->toBe(1)
        ->and(preg_match($pattern, '0501234567'))->toBe(0)
        ->and(preg_match($pattern, '+0501234567'))->toBe(0);
});

it('lists the seven palettes and twelve section keys from the spec', function (): void {
    $schema = tenantSchema();
    $sectionKeys = $schema['properties']['content']['properties']['sections']['items']['properties']['key']['enum'];

    expect($schema['properties']['branding']['properties']['palette']['enum'])
        ->toBe(['sand', 'navy', 'emerald', 'charcoal', 'rose', 'gold', 'custom'])
        ->and($sectionKeys)->toBe(config('themes.sections'));
});

it('has a default for every optional top-level section, and none for required fields', function (): void {
    $schema = tenantSchema();
    $defaults = config('tenant-defaults');

    foreach (array_keys($schema['properties']) as $section) {
        if ($section === '_schema') {
            continue; // document metadata, not a config section
        }
        expect($defaults)->toHaveKey($section);
    }
    expect($defaults['identity'])->not->toHaveKey('display_name')
        ->and($defaults['contact'])->not->toHaveKey('whatsapp');

    // every section key exactly once in the defaults (tenants may reorder them)
    $sectionKeys = array_column($defaults['content']['sections'], 'key');
    expect($sectionKeys)->toEqualCanonicalizing(config('themes.sections'));
});
