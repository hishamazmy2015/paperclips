<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Tenant;
use App\Provisioning\PublishTenant;

final class SitePublishCommand extends SiteLifecycleCommand
{
    protected $signature = 'platform:site:publish {slug}';

    protected $description = 'Publish a draft site: live in ≤ 5 s, no restart';

    public function handle(PublishTenant $publisher): int
    {
        return $this->withTenant((string) $this->argument('slug'), function (Tenant $tenant) use ($publisher): int {
            $already = $tenant->isLive();
            if (! $already) {
                $tenant = $publisher->handle($tenant);
            }

            return $this->emit($this->describe($tenant, ! $already), sprintf('%s %s → %s', $already ? 'Already live:' : 'Published', $tenant->slug, $tenant->url()));
        });
    }
}
