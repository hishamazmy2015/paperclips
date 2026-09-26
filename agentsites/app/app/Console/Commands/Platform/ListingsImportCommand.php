<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Listings\ListingImporter;

/** platform:listings:import — a CSV of listings into one site (spec §14, A5). */
final class ListingsImportCommand extends PlatformCommand
{
    protected $signature = 'platform:listings:import {slug} {file} {--dry-run : Validate only} {--no-media : Keep photo URLs instead of caching the images}';

    protected $description = 'Import listings from a CSV into a site (dedupe by ref, row errors to <file>.errors.csv)';

    public function handle(ListingImporter $importer): int
    {
        $tenant = $this->findTenant((string) $this->argument('slug'));
        if ($tenant === null) {
            return $this->failWith("no tenant with slug '{$this->argument('slug')}'");
        }
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            return $this->failWith("no such file: {$file}");
        }

        $result = $importer->importCsv($tenant, $file, (bool) $this->option('dry-run'), ! (bool) $this->option('no-media'));
        $summary = ['ok' => true, 'slug' => $tenant->slug, 'dry_run' => (bool) $this->option('dry-run')] + $result;
        if ((bool) $this->option('json')) {
            return $this->emit($summary);
        }
        $this->info(sprintf('%s: %d rows — %d created, %d updated, %d errors%s', $tenant->slug, $result['total'], $result['created'], $result['updated'], count($result['errors']), $result['errors_path'] !== null ? ' → '.$result['errors_path'] : ''));
        foreach (array_slice($result['errors'], 0, 20) as $error) {
            $this->line(sprintf('  row %d %s: %s', $error['row'], $error['ref'], $error['error']));
        }

        return self::SUCCESS;
    }
}
