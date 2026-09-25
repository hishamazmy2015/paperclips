<?php

declare(strict_types=1);

use App\Provisioning\AgentCsv;
use App\Provisioning\AgentGenerator;
use App\Themes\ThemeRegistry;

it('is deterministic for a seed and different across seeds', function (): void {
    $generator = app(AgentGenerator::class);

    $a = iterator_to_array($generator->generate(50, seed: 9), false);
    $b = iterator_to_array($generator->generate(50, seed: 9), false);
    $c = iterator_to_array($generator->generate(50, seed: 10), false);

    expect($a)->toBe($b)->and($a)->not->toBe($c);
});

it('produces 1000 distinct, valid agents', function (): void {
    $rows = iterator_to_array(app(AgentGenerator::class)->generate(1000, seed: 1), false);
    $installed = app(ThemeRegistry::class)->installed();

    expect(count($rows))->toBe(1000)
        ->and(count(array_unique(array_column($rows, 'whatsapp'))))->toBe(1000)
        ->and(count(array_unique(array_filter(array_column($rows, 'email')))))->toBe(count(array_filter(array_column($rows, 'email'))))
        ->and(count(array_unique(array_column($rows, 'name'))))->toBeGreaterThan(100);

    foreach ($rows as $row) {
        expect($row['whatsapp'])->toMatch('/^\+9715[024568]\d{7}$/')
            ->and($installed)->toContain($row['theme'])
            ->and(['sand', 'navy', 'emerald', 'charcoal', 'rose', 'gold'])->toContain($row['palette'])
            ->and(['ar', 'en'])->toContain($row['locale'])
            ->and($row['areas'])->not->toBeEmpty();
    }
});

it('round-trips through the CSV contract', function (): void {
    $path = storage_path('app/generator-roundtrip.csv');
    AgentCsv::write($path, app(AgentGenerator::class)->generate(5, seed: 2));

    expect(AgentCsv::header($path))->toBe(AgentCsv::COLUMNS)->and(AgentCsv::count($path))->toBe(5);
    $rows = AgentCsv::rows($path, 2, 3);
    expect(array_keys($rows))->toBe([2, 3]);
    $data = AgentCsv::toData($rows[2]);
    expect($data['name'])->not->toBe('')->and($data['config']['branding']['palette'])->toBeIn(['sand', 'navy', 'emerald', 'charcoal', 'rose', 'gold']);
    @unlink($path);
});
