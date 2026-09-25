<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Tenant;
use App\Provisioning\TenantLifecycle;

final class SiteRestoreCommand extends SiteLifecycleCommand
{
    protected $signature = 'platform:site:restore {slug}';

    protected $description = 'Restore a suspended or deleted (not yet purged) site';

    public function handle(TenantLifecycle $lifecycle): int
    {
        return $this->withTenant((string) $this->argument('slug'), function (Tenant $tenant) use ($lifecycle): int {
            $changed = $tenant->trashed() || $tenant->isSuspended();
            if ($changed) {
                $lifecycle->restore($tenant);
            }

            return $this->emit($this->describe($tenant, $changed), sprintf('%s %s (%s)', $changed ? 'Restored' : 'Nothing to restore:', $tenant->slug, $tenant->status));
        }, withTrashed: true);
    }
}
