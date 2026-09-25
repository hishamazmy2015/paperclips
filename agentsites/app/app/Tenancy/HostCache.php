<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * host → tenant lookup with a cache in front (spec §11 step 3): Redis in production, whatever
 * the default cache store is elsewhere. Entries carry everything the resolver needs so a warm
 * request touches no database. Domain and tenant-status writes must call forgetTenant().
 *
 * The entry carries the tenant's raw attributes so the middleware can hydrate the model
 * without a query; TenantConfig::save and the lifecycle services invalidate it.
 *
 * @phpstan-type HostEntry array{tenant_id: int, slug: string, status: string, role: string, host: string, primary_host: string, theme_key: string, tenant: array<string, mixed>}
 */
final class HostCache
{
    public const TTL_SECONDS = 600;

    public const NEGATIVE_TTL_SECONDS = 60;

    private const MISS = '__miss__';

    public function __construct(private readonly CacheRepository $cache) {}

    public static function key(string $host): string
    {
        return 'host:'.$host;
    }

    /** @return HostEntry|null */
    public function lookup(string $host): ?array
    {
        $host = HostName::normalize($host);
        $cached = $this->cache->get(self::key($host));

        if ($cached === self::MISS) {
            return null;
        }
        if (is_array($cached)) {
            /** @var HostEntry $cached */
            return $cached;
        }

        $entry = $this->fromDatabase($host);
        $this->cache->put(self::key($host), $entry ?? self::MISS, $entry === null ? self::NEGATIVE_TTL_SECONDS : self::TTL_SECONDS);

        return $entry;
    }

    /** @return HostEntry|null */
    private function fromDatabase(string $host): ?array
    {
        $domain = Domain::unscopedByTenant()->where('host', $host)->first();
        if ($domain === null) {
            return null;
        }

        $tenant = Tenant::withTrashed()->find($domain->tenant_id);
        if ($tenant === null) {
            return null;
        }

        $status = $tenant->trashed() ? Tenant::STATUS_DELETED : $tenant->status;

        return [
            'tenant_id' => $tenant->id,
            'slug' => $tenant->slug,
            'status' => $status,
            'role' => $domain->role,
            'host' => $domain->host,
            'primary_host' => $tenant->primaryHost(),
            'theme_key' => $tenant->theme_key,
            'tenant' => $tenant->getAttributes(),
        ];
    }

    public function forgetHost(string $host): void
    {
        $this->cache->forget(self::key(HostName::normalize($host)));
    }

    /** Drop every cached host of a tenant — after any domain, status or slug change. */
    public function forgetTenant(Tenant $tenant): void
    {
        $hosts = Domain::unscopedByTenant()->withTrashed()->where('tenant_id', $tenant->id)->pluck('host');
        foreach ($hosts as $host) {
            $this->forgetHost((string) $host);
        }
        $this->forgetHost($tenant->subdomainHost());
    }
}
