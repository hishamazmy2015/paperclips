<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Tenant;
use App\Provisioning\TenantLifecycle;

final class SiteSuspendCommand extends SiteLifecycleCommand
{
    protected $signature = 'platform:site:suspend {slug} {--reason=}';

    protected $description = 'Suspend a site (paused page, 503) — reversible with platform:site:restore';

    public function handle(TenantLifecycle $lifecycle): int
    {
        return $this->withTenant((string) $this->argument('slug'), function (Tenant $tenant) use ($lifecycle): int {
            $changed = ! $tenant->isSuspended();
            if ($changed) {
                $lifecycle->suspend($tenant, (string) $this->option('reason'));
            }

            return $this->emit($this->describe($tenant, $changed), sprintf('%s %s', $changed ? 'Suspended' : 'Already suspended:', $tenant->slug));
        });
    }
}
