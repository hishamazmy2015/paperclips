<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Tenant;
use App\Provisioning\TenantLifecycle;

final class SiteDeleteCommand extends SiteLifecycleCommand
{
    protected $signature = 'platform:site:delete {slug} {--reason=}';

    protected $description = 'Soft-delete a site; platform:purge removes it after 30 days';

    public function handle(TenantLifecycle $lifecycle): int
    {
        return $this->withTenant((string) $this->argument('slug'), function (Tenant $tenant) use ($lifecycle): int {
            $lifecycle->delete($tenant, (string) $this->option('reason'));
            $purgeAfter = now()->addDays(PurgeCommand::DAYS)->toDateString();

            return $this->emit(['ok' => true, 'slug' => $tenant->slug, 'status' => $tenant->status, 'purge_after' => $purgeAfter], sprintf('Deleted %s (kept until %s)', $tenant->slug, $purgeAfter));
        });
    }
}
