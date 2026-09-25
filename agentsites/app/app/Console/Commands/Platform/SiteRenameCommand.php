<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Tenant;
use App\Provisioning\TenantLifecycle;

final class SiteRenameCommand extends SiteLifecycleCommand
{
    protected $signature = 'platform:site:rename {slug} {new-slug}';

    protected $description = 'Rename a site; the old host 301s to the new one for 90 days';

    public function handle(TenantLifecycle $lifecycle): int
    {
        $newSlug = strtolower(trim((string) $this->argument('new-slug')));

        return $this->withTenant((string) $this->argument('slug'), function (Tenant $tenant) use ($lifecycle, $newSlug): int {
            if ($tenant->slug === $newSlug) {
                return $this->emit($this->describe($tenant, false), 'Already named '.$newSlug);
            }
            $oldHost = $tenant->subdomainHost();
            $lifecycle->rename($tenant, $newSlug);

            return $this->emit(
                $this->describe($tenant) + ['redirect_from' => $oldHost, 'redirect_days' => TenantLifecycle::RENAME_REDIRECT_DAYS],
                sprintf('Renamed → %s (%s redirects for %d days)', $tenant->url(), $oldHost, TenantLifecycle::RENAME_REDIRECT_DAYS),
            );
        });
    }
}
