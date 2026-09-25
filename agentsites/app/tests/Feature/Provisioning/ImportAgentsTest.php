<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\ImportRun;
use App\Models\Tenant;
use App\Provisioning\AgentCsv;
use App\Provisioning\AgentGenerator;
use App\Provisioning\ImportAgents;

// Spec §12 Import and acceptance test A5: CSV rows → sites in batches, --dry-run, per-row
// error file, resumable after a kill.

beforeEach(function (): void {
    $this->importer = app(ImportAgents::class);
    $this->dir = storage_path('app/import-tests');
    if (! is_dir($this->dir)) {
        mkdir($this->dir, 0777, true);
    }
});

afterEach(function (): void {
    foreach (glob($this->dir.'/*') ?: [] as $file) {
        @unlink($file);
    }
});

/** @param  list<array<string, mixed>>  $rows */
function csvFile(string $dir, string $name, array $rows): string
{
    $path = $dir.'/'.$name;
    AgentCsv::write($path, $rows);

    return $path;
}

it('imports the sample CSV: one live site per row, one account per agent', function (): void {
    $run = $this->importer->start(base_path('../samples/agents.csv'));

    expect($run->status)->toBe(ImportRun::STATUS_COMPLETED)
        ->and($run->total_rows)->toBe(3)
        ->and($run->created_rows)->toBe(3)
        ->and($run->error_rows)->toBe(0)
        ->and($run->errors_path)->toBeNull()
        ->and(Tenant::query()->count())->toBe(3)
        ->and(Account::query()->count())->toBe(3)
        ->and(Tenant::query()->where('slug', 'ahmed-al-falasi')->exists())->toBeTrue()
        ->and(Tenant::query()->where('slug', 'john-smith')->exists())->toBeTrue();

    $sara = Tenant::query()->whereNotIn('slug', ['ahmed-al-falasi', 'john-smith'])->firstOrFail();
    expect($sara->displayName())->toBe('سارة المنصوري')->and($sara->defaultLocale())->toBe('ar');

    $this->get('http://ahmed-al-falasi.example.test/en')->assertOk()->assertSee('Falasi Properties')->assertSee('Business Bay');
    $this->get('http://'.$sara->subdomainHost().'/ar')->assertOk()->assertSee('سارة المنصوري')->assertSee('Yas Island');
});

it('is idempotent: importing the same file again creates nothing', function (): void {
    $this->importer->start(base_path('../samples/agents.csv'));
    $second = $this->importer->start(base_path('../samples/agents.csv'));

    expect($second->created_rows)->toBe(0)->and($second->existing_rows)->toBe(3)->and(Tenant::query()->count())->toBe(3);
});

it('validates everything in a dry run without writing, and lists the bad rows', function (): void {
    $path = csvFile($this->dir, 'dry.csv', [
        ['name' => 'Good Agent', 'whatsapp' => '+971501111111'],
        ['name' => 'Bad Phone', 'whatsapp' => '12'],
        ['name' => 'Bad Theme', 'whatsapp' => '+971502222222', 'theme' => 'nope'],
        ['name' => 'Bad Slug', 'whatsapp' => '+971503333333', 'slug' => 'Bad_Slug'],
        ['name' => '', 'whatsapp' => '+971504444444'],
        ['name' => 'Bad Locale', 'whatsapp' => '+971505555555', 'locale' => 'fr'],
    ]);

    $run = $this->importer->dryRun($path);

    expect($run->total_rows)->toBe(6)->and($run->error_rows)->toBe(5)->and(Tenant::query()->count())->toBe(0)->and(ImportRun::query()->count())->toBe(0);
    $errors = array_map('str_getcsv', file($run->errors_path, FILE_IGNORE_NEW_LINES));
    expect($errors[0])->toBe(['row', 'slug', 'error'])
        ->and(array_column(array_slice($errors, 1), 0))->toBe(['2', '3', '4', '5', '6'])
        ->and($errors[1][2])->toContain('phone')
        ->and($errors[2][2])->toContain('theme')
        ->and($errors[3][2])->toContain('slug');
});

it('records per-row errors in <source>.errors.csv and keeps going', function (): void {
    $path = csvFile($this->dir, 'errors.csv', [
        ['name' => 'First Agent', 'whatsapp' => '+971501111111'],
        ['name' => 'Bad Phone', 'whatsapp' => '12'],
        ['name' => 'Third Agent', 'whatsapp' => '+971503333333', 'slug' => 'admin'],
        ['name' => 'Fourth Agent', 'whatsapp' => '+971504444444'],
    ]);

    $run = $this->importer->start($path);

    expect($run->status)->toBe(ImportRun::STATUS_COMPLETED)
        ->and($run->created_rows)->toBe(2)
        ->and($run->error_rows)->toBe(2)
        ->and($run->processed_rows)->toBe(4)
        ->and($run->errors_path)->toBe(ImportAgents::errorsPath($path));

    $errors = array_map('str_getcsv', file($run->errors_path, FILE_IGNORE_NEW_LINES));
    expect(array_column(array_slice($errors, 1), 0))->toBe(['2', '3'])->and($errors[2][2])->toContain('reserved');

    // a new run of the same file starts a fresh error file instead of appending to the old one
    $again = $this->importer->start($path);
    $errors = array_map('str_getcsv', file($again->errors_path, FILE_IGNORE_NEW_LINES));
    expect($again->existing_rows)->toBe(2)
        ->and($again->error_rows)->toBe(2)
        ->and(array_column(array_slice($errors, 1), 0))->toBe(['2', '3']);
});

it('resumes from its checkpoint after a kill', function (): void {
    $rows = iterator_to_array(app(AgentGenerator::class)->generate(250, seed: 3), false);
    $path = csvFile($this->dir, 'resume.csv', $rows);

    // batch 1 runs, then the process dies before batch 2 is queued
    $run = ImportRun::query()->create(['source' => $path, 'status' => ImportRun::STATUS_PENDING, 'options' => ['batch' => 100, 'publish' => true], 'total_rows' => 100]);
    $this->importer->processBatch($run, 1, 100);
    $run->update(['total_rows' => 250, 'status' => ImportRun::STATUS_RUNNING]);
    expect($run->last_row)->toBe(100)->and(Tenant::query()->count())->toBe(100);

    $resumed = $this->importer->resume($run);

    expect($resumed->status)->toBe(ImportRun::STATUS_COMPLETED)
        ->and($resumed->processed_rows)->toBe(250)
        ->and($resumed->created_rows)->toBe(250)
        ->and($resumed->last_row)->toBe(250)
        ->and(Tenant::query()->count())->toBe(250);

    expect($this->importer->resume($resumed)->status)->toBe(ImportRun::STATUS_COMPLETED);
});

it('imports 1000 generated agents well inside 15 minutes and every sampled host resolves (A5)', function (): void {
    $rows = iterator_to_array(app(AgentGenerator::class)->generate(1000, seed: 42), false);
    $path = csvFile($this->dir, 'thousand.csv', $rows);

    $started = microtime(true);
    $run = $this->importer->start($path);
    $seconds = microtime(true) - $started;

    expect($run->status)->toBe(ImportRun::STATUS_COMPLETED)
        ->and($run->created_rows)->toBe(1000)
        ->and($run->error_rows)->toBe(0)
        ->and($seconds)->toBeLessThan(900)
        ->and(Tenant::query()->live()->count())->toBe(1000)
        ->and(Account::query()->count())->toBe(1000);

    // every site is a different agent with its own config
    $tenants = Tenant::query()->get();
    expect($tenants->map(fn (Tenant $t): string => $t->displayName())->unique()->count())->toBeGreaterThan(300)
        ->and($tenants->map(fn (Tenant $t): string => (string) data_get($t->config, 'branding.palette'))->unique()->count())->toBe(6)
        ->and($tenants->map(fn (Tenant $t): string => $t->defaultLocale())->unique()->sort()->values()->all())->toBe(['ar', 'en'])
        ->and($tenants->pluck('slug')->unique()->count())->toBe(1000);

    foreach ($tenants->random(20) as $tenant) {
        $this->get('http://'.$tenant->subdomainHost().'/'.$tenant->defaultLocale())
            ->assertOk()
            ->assertSee(e($tenant->displayName()), false)
            ->assertSee('--c-primary:'.config('palettes.palettes.'.data_get($tenant->config, 'branding.palette').'.primary'), false);
    }

    fwrite(STDERR, sprintf("\n[scale] 1000 sites imported in %.1f s (%.0f ms/site)\n", $seconds, $seconds * 1000 / 1000));
})->group('scale');
