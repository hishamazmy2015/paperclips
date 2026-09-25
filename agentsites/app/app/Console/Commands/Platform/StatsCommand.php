<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Account;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

/** platform:stats — headline numbers for operators and the admin metrics page (spec §15). */
final class StatsCommand extends PlatformCommand
{
    protected $signature = 'platform:stats';

    protected $description = 'Tenants by status, accounts, listings, leads and events';

    public function handle(): int
    {
        $byStatus = Tenant::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn (mixed $n): int => (int) $n)->all();

        $stats = TenantContext::global(fn (): array => [
            'tenants' => ['total' => Tenant::query()->count(), 'by_status' => $byStatus, 'published_7d' => Tenant::query()->where('published_at', '>=', now()->subDays(7))->count(), 'deleted_pending_purge' => Tenant::onlyTrashed()->count()],
            'accounts' => ['total' => Account::query()->count(), 'trialing' => Account::query()->where('plan_status', 'trialing')->count()],
            'listings' => ['real' => Listing::unscopedByTenant()->real()->count(), 'demo' => Listing::unscopedByTenant()->where('source', 'demo')->count()],
            'leads' => ['today' => Lead::unscopedByTenant()->where('created_at', '>=', now()->startOfDay())->count(), 'total' => Lead::unscopedByTenant()->count()],
            'events_24h' => Event::query()->where('created_at', '>=', now()->subDay())->count(),
        ]);

        return $this->emit(['ok' => true] + $stats);
    }
}
