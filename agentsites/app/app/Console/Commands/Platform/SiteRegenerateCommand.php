<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Caching\Regenerator;
use App\Models\RegenerateRun;

/** platform:site:regenerate — purge + warm one site, or every live site in resumable batches (spec §16, A5). */
final class SiteRegenerateCommand extends PlatformCommand
{
    protected $signature = 'platform:site:regenerate {slug? : One site}
        {--all : Every live site, in batches through the queue}
        {--resume= : Continue a killed --all run by id}
        {--status= : Show a run}
        {--batch=50 : Tenants per batch}
        {--no-warm : Purge only}';

    protected $description = 'Purge the page cache and re-render the main pages (one site, or --all with a checkpoint)';

    public function handle(Regenerator $regenerator): int
    {
        $warm = ! (bool) $this->option('no-warm');

        if (($status = $this->option('status')) !== null) {
            $run = RegenerateRun::query()->find((int) $status);

            return $run === null ? $this->failWith("no run #{$status}") : $this->emit($run->summary());
        }
        if (($resume = $this->option('resume')) !== null) {
            $run = RegenerateRun::query()->find((int) $resume);
            if ($run === null) {
                return $this->failWith("no run #{$resume}");
            }
            $run = $regenerator->resume($run);

            return $this->emit($run->summary(), sprintf('Run #%d %s: %d/%d sites', $run->id, $run->status, $run->processed_tenants, $run->total_tenants));
        }
        if ((bool) $this->option('all')) {
            $run = $regenerator->startAll((int) $this->option('batch'), $warm);

            return $this->emit($run->summary(), sprintf('Run #%d %s: %d/%d sites, %d pages warmed', $run->id, $run->status, $run->processed_tenants, $run->total_tenants, $run->warmed_pages));
        }

        $slug = (string) $this->argument('slug');
        if ($slug === '') {
            return $this->failWith('give a slug, or --all');
        }
        $tenant = $this->findTenant($slug);
        if ($tenant === null) {
            return $this->failWith("no tenant with slug '{$slug}'");
        }
        $result = $regenerator->one($tenant, $warm);

        return $this->emit(['ok' => true, 'slug' => $tenant->slug] + $result, sprintf('%s: cache purged, %d pages warmed', $tenant->slug, $result['warmed']));
    }
}
