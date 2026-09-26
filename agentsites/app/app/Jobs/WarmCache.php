<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Caching\SiteWarmer;
use App\Models\Tenant;
use App\Tenancy\HostCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Spec §12.3 / §16: prime the host cache and pre-render home, listings, contact, about and the
 * area pages so the first visitor hits a warm page cache. Rendering needs a console process
 * (queue worker or CLI); inside an HTTP request with a sync queue only the host cache is primed.
 */
final class WarmCache implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $tenantId)
    {
        $this->afterCommit();
    }

    public function handle(HostCache $hosts, SiteWarmer $warmer): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }
        $hosts->lookup($tenant->primaryHost());
        $warmer->warm($tenant);
    }
}
